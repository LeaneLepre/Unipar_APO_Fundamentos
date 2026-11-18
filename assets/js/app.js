document.addEventListener('DOMContentLoaded', () => {

  /* ---- Confirmação antes de excluir (formulários com data-confirm) ---- */
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', ev => {
      if (!window.confirm(form.dataset.confirm)) ev.preventDefault();
    });
  });

  /* ---- Máscaras de CEP, CPF/CNPJ e telefone ---- */
  const masks = {
    cep: v => { v = v.replace(/\D/g, '').slice(0, 8); return v.length > 5 ? v.slice(0, 5) + '-' + v.slice(5) : v; },
    cpfcnpj: v => {
      v = v.replace(/\D/g, '').slice(0, 14);
      if (v.length <= 11) {
        return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      }
      return v.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
              .replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
    },
    phone: v => {
      v = v.replace(/\D/g, '').slice(0, 11);
      if (v.length > 10) return v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
      if (v.length > 6)  return v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
      if (v.length > 2)  return v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
      return v;
    },
  };
  document.querySelectorAll('[data-mask]').forEach(input => {
    const fn = masks[input.dataset.mask];
    if (!fn) return;
    input.addEventListener('input', () => { input.value = fn(input.value); });
    input.value = fn(input.value);
  });

  /* ---- Endereços: UF só com letras e preenchimento automático pelo CEP (ViaCEP) ---- */
  const addressForm = document.getElementById('address-form');
  if (addressForm) {
    const state = addressForm.querySelector('#state');
    state.addEventListener('input', () => { state.value = state.value.replace(/[^a-zA-Z]/g, '').toUpperCase(); });

    const zip = addressForm.querySelector('#zip');
    const status = document.getElementById('zip-status');
    const original = status.textContent;
    zip.addEventListener('input', async () => {
      const digits = zip.value.replace(/\D/g, '');
      if (digits.length !== 8) { status.textContent = original; return; }
      status.textContent = 'Buscando CEP...';
      try {
        const res = await fetch('https://viacep.com.br/ws/' + digits + '/json/');
        const d = await res.json();
        if (d.erro) { status.textContent = 'CEP não encontrado. Preencha os campos manualmente.'; return; }
        const set = (id, v) => { const el = addressForm.querySelector('#' + id); if (el && v) el.value = v; };
        set('street', d.logradouro); set('city', d.localidade); set('state', d.uf);
        status.textContent = 'Endereço preenchido pelo CEP. Confira e complete o número.';
        addressForm.querySelector('#number').focus();
      } catch (err) {
        status.textContent = 'Não foi possível consultar o CEP agora. Preencha os campos manualmente.';
      }
    });
  }

  /* ---- Fretes: status atualizado ao mudar o select ---- */
  document.querySelectorAll('.status-form select').forEach(sel => {
    sel.addEventListener('change', () => sel.form.submit());
  });

  /* ---- Fretes: produtos dinâmicos ---- */
  const freightForm = document.getElementById('freight-form');
  if (!freightForm) return;

  const rowsBox = document.getElementById('product-rows');
  const tpl = document.getElementById('product-row-template');
  const addBtn = document.getElementById('add-product');
  const summary = document.getElementById('freight-summary');
  const origin = document.getElementById('origin_id');
  const destination = document.getElementById('destination_id');

  const selects = () => [...rowsBox.querySelectorAll('select[name="products[]"]')];

  function refresh() {
    const rows = rowsBox.querySelectorAll('.product-row');
    const chosen = selects().map(s => s.value).filter(Boolean);

    // Não permite escolher o mesmo produto em duas linhas.
    selects().forEach(sel => {
      [...sel.options].forEach(opt => {
        opt.disabled = opt.value !== '' && chosen.includes(opt.value) && opt.value !== sel.value;
      });
    });

    // Mantém sempre ao menos uma linha.
    rows.forEach(r => { r.querySelector('.btn-remove-product').disabled = rows.length === 1; });

    // Não há mais produtos para adicionar?
    const total = (selects()[0] ? selects()[0].options.length - 1 : 0);
    addBtn.disabled = rows.length >= total;

    // Resumo: quantidade, peso total e valor total.
    let weight = 0, value = 0;
    selects().forEach(sel => {
      const o = sel.selectedOptions[0];
      if (o && o.value) { weight += parseFloat(o.dataset.weight) || 0; value += parseFloat(o.dataset.value) || 0; }
    });
    const fmt = (n, opts) => n.toLocaleString('pt-BR', opts);
    summary.textContent = chosen.length
      ? chosen.length + ' produto(s) · ' + fmt(weight, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kg · '
        + fmt(value, { style: 'currency', currency: 'BRL' })
      : 'Nenhum produto selecionado.';
  }

  addBtn.addEventListener('click', () => {
    rowsBox.appendChild(tpl.content.cloneNode(true));
    refresh();
    const last = selects().pop();
    if (last) last.focus();
  });

  rowsBox.addEventListener('click', ev => {
    const btn = ev.target.closest('.btn-remove-product');
    if (!btn || btn.disabled) return;
    btn.closest('.product-row').remove();
    refresh();
  });
  rowsBox.addEventListener('change', refresh);

  // Origem e destino não podem ser o mesmo endereço.
  function syncAddresses() {
    [[origin, destination], [destination, origin]].forEach(([a, b]) => {
      [...a.options].forEach(o => { o.disabled = o.value !== '' && o.value === b.value; });
    });
  }
  origin.addEventListener('change', syncAddresses);
  destination.addEventListener('change', syncAddresses);

  // Evita envio sem produto escolhido.
  freightForm.addEventListener('submit', ev => {
    if (!selects().some(s => s.value)) {
      ev.preventDefault();
      summary.textContent = 'Escolha pelo menos um produto.';
      selects()[0]?.focus();
    }
  });

  syncAddresses();
  refresh();
});