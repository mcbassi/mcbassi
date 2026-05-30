<?php
declare(strict_types=1);

namespace App\Atividades;

use App\Auth\AuthService;
use App\Infra\Database;
use App\Security\Csrf;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use RuntimeException;
use Throwable;

use function app_path;
use function csrf_token;
use function flash_set;
use function redirect;
use function session_user_email;
use function session_user_name;
use function url;
use function t;

final class ActivityController
{
    private AuthService $auth;
    private Database $database;
    private Request $request;
    private ActivityRepository $repository;

    public function __construct(?AuthService $auth = null, ?Database $database = null, ?Request $request = null, ?ActivityRepository $repository = null)
    {
        $this->request = $request ?? Request::capture();
        $this->database = $database ?? new Database();
        $this->auth = $auth ?? new AuthService($this->request);
        $this->repository = $repository ?? new ActivityRepository($this->database);
    }

    public function crud(Request $request): string
    {
        $this->index();
        return '';
    }

    public function projectView(Request $request): string
    {
        $this->index();
        return '';
    }

    public function index(): void
    {
        $this->auth->requireAuth();

        try {
            $tree = $this->repository->tree();
            $templatesTree = $this->repository->templatesTree();
            $selectedProject = trim((string) ($_GET['projeto'] ?? ''));
            $selectedSubproject = trim((string) ($_GET['subprojeto'] ?? ''));

            if ($selectedProject === '' && isset($tree[0])) {
                $selectedProject = (string) ($tree[0]['projeto'] ?? '');
                $selectedSubproject = (string) ($tree[0]['subprojeto'] ?? '');
            }

            $activities = $this->repository->activities(
                $selectedProject !== '' ? $selectedProject : null,
                $selectedSubproject !== '' ? $selectedSubproject : null
            );

            View::render('atividades/project_view', [
                'pageTitle' => t('activity.menu_title'),
                'contentTitle' => t('activity.menu_title'),
                'subtitle' => t('activity.project_view'),
                'tree' => $tree,
                'templatesTree' => $templatesTree,
                'activities' => $activities,
                'selectedProject' => $selectedProject,
                'selectedSubproject' => $selectedSubproject,
                'stats' => $this->repository->stats(),
                'supportsDataInicio' => $this->repository->hasDataInicio(),
                'supportsDependencyTable' => $this->repository->hasDependenciesTable(),
                'flashSuccess' => $_SESSION['_flash']['success'] ?? null,
                'flashError' => $_SESSION['_flash']['error'] ?? null,
            ]);
            unset($_SESSION['_flash']['success'], $_SESSION['_flash']['error']);
        } catch (Throwable $e) {
            View::render('atividades/error', [
                'pageTitle' => t('activity.menu_title'),
                'contentTitle' => t('activity.menu_title'),
                'subtitle' => t('activity.project_view'),
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function edit(): void
    {
        $this->auth->requireAuth();

        $id = (int) ($_GET['id'] ?? 0);
        $row = $id > 0 ? $this->repository->find($id) : null;

        if ($id > 0 && $row === null) {
            flash_set('error', t('activity.not_found'));
            redirect(url('atividades/index.php'));
        }

        $project = trim((string) ($_GET['projeto'] ?? ($row['projeto'] ?? '')));
        $subproject = trim((string) ($_GET['subprojeto'] ?? ($row['subprojeto'] ?? '')));

        $row ??= [
            'id' => 0,
            'projeto' => $project,
            'subprojeto' => $subproject,
            'status_atual' => 'Planejado',
        ];

        View::render('atividades/edit', [
            'pageTitle' => $id > 0 ? t('activity.edit_activity') : t('activity.new_activity'),
            'contentTitle' => $id > 0 ? t('activity.edit_activity') : t('activity.new_activity'),
            'subtitle' => t('activity.project_view'),
            'row' => $row,
            'supportsDataInicio' => $this->repository->hasDataInicio(),
            'supportsDependencyTable' => $this->repository->hasDependenciesTable(),
            'dependencyCandidates' => $this->repository->dependencyCandidates($id),
            'dependencyIds' => $id > 0 ? $this->repository->dependencyIds($id) : [],
            'evidences' => $id > 0 ? $this->repository->evidences($id) : [],
        ]);
    }

    public function save(): void
    {
        $this->auth->requireAuth();

        if (!Csrf::check((string) ($_POST['_csrf'] ?? ''))) {
            flash_set('error', t('activity.invalid_token_reload'));
            redirect(url('atividades/index.php'));
        }

        try {
            $id = $this->repository->save($_POST, session_user_email(), session_user_name());
            $deps = array_map('intval', $_POST['dependencies'] ?? []);
            $this->repository->saveDependencies($id, $deps);
            flash_set('success', t('activity.saved_success'));
            redirect(url('atividades/edit.php?id=' . $id));
        } catch (Throwable $e) {
            flash_set('error', t('activity.error_save', ['message' => $e->getMessage()]));
            $suffix = isset($_POST['id']) && (int) $_POST['id'] > 0 ? '?id=' . (int) $_POST['id'] : '';
            redirect(url('atividades/edit.php' . $suffix));
        }
    }

    public function api(): void
    {
        $this->auth->requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        if (!Csrf::check((string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')))) {
            Response::json(['ok' => false, 'message' => t('activity.invalid_token')], 419);
        }

        $action = trim((string) ($_POST['action'] ?? ''));

        try {
            switch ($action) {
                case 'save_row':
                    $id = $this->repository->save($_POST, session_user_email(), session_user_name());
                    Response::json(['ok' => true, 'id' => $id, 'message' => t('activity.saved_short')]);

                case 'import_templates':
                    $count = $this->repository->importTemplates(
                        trim((string) ($_POST['projeto'] ?? '')) ?: null,
                        trim((string) ($_POST['subprojeto'] ?? '')) ?: null,
                        session_user_email(),
                        session_user_name()
                    );
                    Response::json(['ok' => true, 'count' => $count, 'message' => t('activity.imported_count', ['count' => $count])]);

                case 'delete':
                    $this->repository->delete((int) ($_POST['id'] ?? 0));
                    Response::json(['ok' => true, 'message' => t('activity.deleted')]);

                default:
                    Response::json(['ok' => false, 'message' => t('activity.invalid_action')], 400);
            }
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function uploadEvidence(): void
    {
        $this->auth->requireAuth();

        if (!Csrf::check((string) ($_POST['_csrf'] ?? ''))) {
            flash_set('error', t('activity.invalid_token'));
            redirect(url('atividades/index.php'));
        }

        $activityId = (int) ($_POST['atividade_id'] ?? 0);
        if ($activityId <= 0 || $this->repository->find($activityId) === null) {
            flash_set('error', t('activity.invalid_activity_upload'));
            redirect(url('atividades/index.php'));
        }

        try {
            $uploaded = $this->storeUploadedFiles($activityId);
            flash_set('success', t('activity.evidence_uploaded_count', ['count' => $uploaded]));
        } catch (Throwable $e) {
            flash_set('error', t('activity.upload_error', ['message' => $e->getMessage()]));
        }

        redirect(url('atividades/edit.php?id=' . $activityId));
    }

    public function deleteEvidence(): void
    {
        $this->auth->requireAuth();

        if (!Csrf::check((string) ($_POST['_csrf'] ?? ''))) {
            flash_set('error', t('activity.invalid_token'));
            redirect(url('atividades/index.php'));
        }

        $activityId = (int) ($_POST['atividade_id'] ?? 0);
        $evidenceId = (int) ($_POST['evidence_id'] ?? 0);

        if ($evidenceId > 0) {
            $relative = $this->repository->deleteEvidence($evidenceId);
            if ($relative !== null) {
                $absolute = app_path('public/' . ltrim($relative, '/'));
                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }
        }

        flash_set('success', t('activity.evidence_removed'));
        redirect(url('atividades/edit.php?id=' . $activityId));
    }

    public function gerar(Request $request): array
    {
        return ['ok' => true, 'message' => t('activity.use_import_button')];
    }

    private function storeUploadedFiles(int $activityId): int
    {
        if (!isset($_FILES['evidencias'])) {
            return 0;
        }

        $files = $_FILES['evidencias'];
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];

        $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip'];
        $dir = app_path('public/uploads/atividades/' . $activityId);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(t('activity.upload_dir_error'));
        }

        $count = 0;
        foreach ($names as $i => $name) {
            $error = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException(t('activity.file_upload_failed', ['file' => (string) $name]));
            }

            $tmp = (string) ($tmpNames[$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }

            $originalName = basename((string) $name);
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                throw new RuntimeException(t('activity.extension_not_allowed', ['ext' => $ext]));
            }

            $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME)) ?: 'evidencia';
            $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . '.' . $ext;
            $target = $dir . DIRECTORY_SEPARATOR . $fileName;

            if (!move_uploaded_file($tmp, $target)) {
                throw new RuntimeException(t('activity.file_write_error', ['file' => $originalName]));
            }

            $relative = 'uploads/atividades/' . $activityId . '/' . $fileName;
            $this->repository->addEvidence($activityId, $relative, $originalName);
            $count++;
        }

        return $count;
    }
}
