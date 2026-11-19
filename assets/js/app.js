document.addEventListener('DOMContentLoaded', () => {

  /* pede confirmação antes de excluir */
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', ev => {
      if (!window.confirm(form.dataset.confirm)) ev.preventDefault();
    });
  });

  /* sem validação de CEP, CPF/CNPJ e telefone */
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

  /* estado só com letras */
  const addressForm = document.getElementById('address-form');
  if (addressForm) {
    const state = addressForm.querySelector('#state');
    state.addEventListener('input', () => { state.value = state.value.replace(/[^a-zA-Z]/g, '').toUpperCase(); });
  }

  /* frete atualiza o status qdo muda o select */
  document.querySelectorAll('.status-form select').forEach(sel => {
    sel.addEventListener('change', () => sel.form.submit());
  });

  /* frete com produtos dinâmicos */
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

    // não deixa escolher o mesmo produto para mais de 1 linha
    selects().forEach(sel => {
      [...sel.options].forEach(opt => {
        opt.disabled = opt.value !== '' && chosen.includes(opt.value) && opt.value !== sel.value;
      });
    });

    // tem sempre pelo menos uma linha
    rows.forEach(r => { r.querySelector('.btn-remove-product').disabled = rows.length === 1; });

    // evita linhas a mais ou repetidas
    const total = (selects()[0] ? selects()[0].options.length - 1 : 0);
    addBtn.disabled = rows.length >= total;

    // faz o resumo dos produtos selecionados (quantidade, peso e valor total)
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

  // a origem e o destino não podem ser o mesmo endereço
  function syncAddresses() {
    [[origin, destination], [destination, origin]].forEach(([a, b]) => {
      [...a.options].forEach(o => { o.disabled = o.value !== '' && o.value === b.value; });
    });
  }
  origin.addEventListener('change', syncAddresses);
  destination.addEventListener('change', syncAddresses);

  // evita enviar sem pelo menos 1 produto escolhido
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