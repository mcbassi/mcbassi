<?php
declare(strict_types=1);

use App\Admin\AdminController;
use App\Analitica\AnaliticaController;
use App\Atividades\ActivityController;
use App\Estrategica\EstrategicaController;
use App\Fields\FieldController;
use App\Grupos\GroupController;
use App\Home\HomeController;
use App\Papers\PaperController;
use App\Prioridades\PrioridadesController;
use App\TelaSql\SqlController;
use App\Wms\WmsController;
use App\Ppt\PptController;
 use App\Clientes\ClienteController;

return [
    ['GET', '/', [HomeController::class, 'index']],
    ['GET', '/health/ping', [HomeController::class, 'ping']],

    ['GET', '/admin/responses', [AdminController::class, 'responses']],

    ['GET', '/analitica', [AnaliticaController::class, 'index']],
    ['GET', '/analitica/final-report', [AnaliticaController::class, 'finalReport']],
    ['GET', '/analitica/export-word', [AnaliticaController::class, 'exportWord']],
    ['GET', '/analitica/prefill', [AnaliticaController::class, 'prefill']],
    ['GET', '/analitica/duvidas', [AnaliticaController::class, 'duvidas']],

    ['GET', '/estrategica', [EstrategicaController::class, 'index']],
    ['POST', '/estrategica/api', [EstrategicaController::class, 'api'], ['csrf' => true]],
    ['GET', '/estrategica/final-report', [EstrategicaController::class, 'finalReport']],

    ['GET', '/prioridades', [PrioridadesController::class, 'index']],
    ['POST', '/prioridades/api', [PrioridadesController::class, 'api'], ['csrf' => true]],
    ['GET', '/prioridades/group-report', [PrioridadesController::class, 'groupReport']],

    ['GET', '/grupos', [GroupController::class, 'index']],
    ['GET', '/grupos/estrategicas-list', [GroupController::class, 'estrategicasList']],
    ['GET', '/grupos/prioridades-list', [GroupController::class, 'prioridadesList']],

    ['GET', '/atividades/crud', [ActivityController::class, 'crud']],
    ['POST', '/atividades/gerar', [ActivityController::class, 'gerar'], ['csrf' => true]],
    ['GET', '/atividades/project-view', [ActivityController::class, 'projectView']],

    ['GET', '/fields', [FieldController::class, 'index']],
    ['GET', '/fields/form', [FieldController::class, 'form']],
    ['POST', '/fields/form', [FieldController::class, 'save'], ['csrf' => true]],
    ['POST', '/fields/delete', [FieldController::class, 'delete'], ['csrf' => true]],
    ['POST', '/fields/import-from-array', [FieldController::class, 'importFromArray'], ['csrf' => true]],

    ['GET', '/papers', [PaperController::class, 'index']],
    ['GET', '/papers/form', [PaperController::class, 'form']],
    ['POST', '/papers/save', [PaperController::class, 'save'], ['csrf' => true]],
    ['POST', '/papers/delete', [PaperController::class, 'delete'], ['csrf' => true]],
    ['GET', '/papers/view', [PaperController::class, 'view']],
    ['GET', '/papers/report', [PaperController::class, 'report']],
    ['GET', '/papers/import', [PaperController::class, 'import']],
    ['POST', '/papers/api/import', [PaperController::class, 'importApi'], ['csrf' => true]],
    ['POST', '/papers/api/sync-dropbox', [PaperController::class, 'syncDropbox'], ['csrf' => true]],
    ['POST', '/papers/api/sync-prompts-responses', [PaperController::class, 'syncPromptsResponses'], ['csrf' => true]],

    ['GET', '/tela_sql', [SqlController::class, 'index']],
    ['GET', '/tela_sql/embed', [SqlController::class, 'embed']],
    ['POST', '/tela_sql/api/catalog-get', [SqlController::class, 'catalogGet'], ['csrf' => true]],
    ['GET', '/tela_sql/api/catalog-list', [SqlController::class, 'catalogList']],
    ['POST', '/tela_sql/api/catalog-save', [SqlController::class, 'catalogSave'], ['csrf' => true]],
    ['POST', '/tela_sql/api/execute', [SqlController::class, 'execute'], ['csrf' => true]],
    ['GET', '/tela_sql/api/schema', [SqlController::class, 'schema']],
    ['POST', '/tela_sql/api/validate', [SqlController::class, 'validate'], ['csrf' => true]],

    ['GET', '/ppt', [PptController::class, 'index']],

    ['GET', '/wms/dashboard', [WmsController::class, 'dashboard']],
    ['GET', '/wms/dashboard-api', [WmsController::class, 'dashboardApi']],

    ['POST', '/api/submit', [HomeController::class, 'submit'], ['csrf' => true]],
    ['GET', '/api/status-questionario', [HomeController::class, 'statusQuestionario']],
    ['POST', '/api/chatgpt-exec', [HomeController::class, 'chatgptExec'], ['csrf' => true]],
    ['POST', '/api/prompts-sync', [HomeController::class, 'promptsSync'], ['csrf' => true]],

    ['GET',  '/clientes',          [ClienteController::class, 'index']],
    ['GET',  '/clientes/cadastro', [ClienteController::class, 'cadastro']],
    ['GET',  '/clientes/faturamento', [ClienteController::class, 'faturamento']],
    ['GET',  '/clientes/planos', [ClienteController::class, 'planos']],
    ['POST', '/clientes/cadastro', [ClienteController::class, 'cadastroSalvar'], ['csrf' => true]],
    ['POST', '/clientes/planos', [ClienteController::class, 'planosSalvar'], ['csrf' => true]],
];
