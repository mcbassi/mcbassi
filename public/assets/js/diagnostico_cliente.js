(() => {
  const form = document.querySelector('[data-questionnaire-form]');
  if (!form) {
    return;
  }

  const stats = {
    total: document.querySelector('[data-stat-total]'),
    answered: document.querySelector('[data-stat-answered]'),
    pending: document.querySelector('[data-stat-pending]'),
    requiredPending: document.querySelector('[data-stat-required-pending]')
  };

  const fields = Array.from(form.querySelectorAll('[data-question-field="true"]'));

  const getFieldValue = (field) => {
    const type = field.dataset.fieldType || 'text';

    if (type === 'checkbox') {
      return field.querySelectorAll('input[type="checkbox"]:checked').length > 0;
    }

    if (type === 'radio') {
      return field.querySelector('input[type="radio"]:checked') !== null;
    }

    const control = field.querySelector('input:not([type="checkbox"]):not([type="radio"]), select, textarea');
    if (!control) {
      return false;
    }

    return String(control.value || '').trim() !== '';
  };

  const applyFieldState = (field) => {
    const answered = getFieldValue(field);
    const required = field.dataset.required === '1';

    field.classList.remove(
      'question-field--state-answered',
      'question-field--state-optional-pending',
      'question-field--state-required-pending'
    );

    if (answered) {
      field.classList.add('question-field--state-answered');
      return { answered: true, requiredPending: false };
    }

    if (required) {
      field.classList.add('question-field--state-required-pending');
      return { answered: false, requiredPending: true };
    }

    field.classList.add('question-field--state-optional-pending');
    return { answered: false, requiredPending: false };
  };

  const recalc = () => {
    const total = fields.length;
    let answered = 0;
    let requiredPending = 0;

    fields.forEach((field) => {
      const state = applyFieldState(field);
      if (state.answered) {
        answered += 1;
      }
      if (state.requiredPending) {
        requiredPending += 1;
      }
    });

    if (stats.total) stats.total.textContent = String(total);
    if (stats.answered) stats.answered.textContent = String(answered);
    if (stats.pending) stats.pending.textContent = String(Math.max(total - answered, 0));
    if (stats.requiredPending) stats.requiredPending.textContent = String(requiredPending);
  };

  form.addEventListener('input', recalc);
  form.addEventListener('change', recalc);
  recalc();
})();
