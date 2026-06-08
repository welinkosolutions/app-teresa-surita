<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<script>
/* BLOCO 1: máscaras + normalização */
$('#telefone').mask('(00) 00000-0000');
$('#cep').mask('00000-000');

function normalizarCampo(el){
  el.value = el.value
    .toLowerCase()
    .replace(/\s+/g,' ')
    .trim()
    .replace(/\b\w/g, l => l.toUpperCase());
}

/* BLOCO 2: radios */
document.querySelectorAll('.label-radio').forEach(label => {
  label.addEventListener('click', () => {
    const input = label.querySelector('input');
    const nome = input.name;

    document.querySelectorAll(`input[name="${nome}"]`).forEach(r => {
      r.closest('.label-radio').classList.remove('active');
    });

    input.checked = true;
    label.classList.add('active');
  });
});

/* BLOCO 3: wizard */
let step = 0;
const steps = document.querySelectorAll('.step');
const progressBar = document.getElementById('progressBar');

function show(i){
  steps.forEach(s => s.classList.remove('active'));
  steps[i].classList.add('active');
  progressBar.style.width = ((i + 1) / steps.length * 100) + '%';
}

function nextStep(){
  if (step < steps.length - 1) {
    step++;
    show(step);
  }
}

function prevStep(){
  if (step > 0) {
    step--;
    show(step);
  }
}

show(0);

/* BLOCO 4: limpar erros */
function limparErrosStep1(){
  ['erro_nome','erro_data','erro_mae','erro_sexo','erro_tel'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
}

/* BLOCO 5: toast */
function toast(msg, type = 'error'){
  const el = document.getElementById('toast');

  el.className = 'toast-elab show';

  if (type === 'success') el.classList.add('success');
  if (type === 'warn') el.classList.add('warn');

  el.innerHTML = msg;

  setTimeout(() => {
    el.classList.remove('show');
  }, 3200);
}

/* BLOCO 6: validação step 1 */
async function validarStep1(){
  limparErrosStep1();

  const nome = document.querySelector('[name="nome"]').value.trim();
  const data = document.getElementById('data_nascimento').value.trim();
  const mae = document.querySelector('[name="nome_mae"]').value.trim();
  const sexo = document.querySelector('input[name="sexo"]:checked');
  const tel = document.getElementById('telefone').value.replace(/\D/g, '');

  let ok = true;

  if (!nome) {
    document.getElementById('erro_nome').style.display = 'block';
    ok = false;
  }

  if (!data) {
    document.getElementById('erro_data').style.display = 'block';
    ok = false;
  }

  if (!mae) {
    document.getElementById('erro_mae').style.display = 'block';
    ok = false;
  }

  if (!sexo) {
    document.getElementById('erro_sexo').style.display = 'block';
    ok = false;
  }

  if (tel.length !== 11) {
    document.getElementById('erro_tel').style.display = 'block';
    ok = false;
  }

  if (!ok) return;

  /* validar idade */
  const nasc = new Date(data);
  const hoje = new Date();

  let idade = hoje.getFullYear() - nasc.getFullYear();
  const m = hoje.getMonth() - nasc.getMonth();

  if (m < 0 || (m === 0 && hoje.getDate() < nasc.getDate())) {
    idade--;
  }

  if (idade < 16) {
    toast('É necessário ter pelo menos 16 anos para participar', 'warn');
    return;
  }

  /* verificar telefone */
  try {
    const r = await fetch('/api/pessoas/verificar-telefone.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ telefone: tel })
    });

    const j = await r.json();

    if (j.existe || j.status === 'existe') {
      toast('<i class="bi bi-exclamation-circle-fill"></i> Este telefone já está cadastrado! Use outro contato.', 'warn');
      return;
    }
  } catch (e) {
    toast('Não foi possível validar o telefone agora. Tente novamente.', 'warn');
    return;
  }

  nextStep();
}

/* BLOCO 7: CEP */
$('#cep').on('blur', function(){
  const c = this.value.replace(/\D/g, '');

  if (c.length !== 8) return;

  fetch(`https://viacep.com.br/ws/${c}/json/`)
    .then(r => r.json())
    .then(j => {
      if (j.erro) return;
      $('[name=endereco]').val(j.logradouro || '');
      $('[name=bairro]').val(j.bairro || '');
      $('[name=cidade]').val(j.localidade || '');
      $('[name=estado]').val(j.uf || '');
    });
});

/* BLOCO 8: validar endereço */
function validarEndereco(){
  const cep = document.querySelector('[name="cep"]').value.trim();
  const endereco = document.querySelector('[name="endereco"]').value.trim();
  const numero = document.querySelector('[name="numero"]').value.trim();
  const bairro = document.querySelector('[name="bairro"]').value.trim();
  const cidade = document.querySelector('[name="cidade"]').value.trim();
  const estado = document.querySelector('[name="estado"]').value.trim();

  if (!cep || !endereco || !numero || !bairro || !cidade || !estado) {
    toast('<i class="bi bi-exclamation-circle-fill"></i> Preencha o endereço completo', 'warn');
    return;
  }

  nextStep();
}

/* BLOCO 9: GPS */
if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(
    pos => {
      document.getElementById('latitude').value = pos.coords.latitude;
      document.getElementById('longitude').value = pos.coords.longitude;
    },
    () => {
      console.warn('GPS não autorizado');
    }
  );
}

/* BLOCO 10: helpers final */
function formatarTelefoneExibicao(tel){
  const d = String(tel || '').replace(/\D/g, '').slice(0, 11);

  if (d.length !== 11) return tel || '';

  return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;
}

function escapeHtml(str){
  return String(str ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

window.copiarPinConvite = async function(pin){
  try {
    await navigator.clipboard.writeText(String(pin));
    toast('<i class="bi bi-check-circle-fill"></i> PIN copiado com sucesso', 'success');
  } catch (e) {
    toast('Não foi possível copiar o PIN automaticamente', 'warn');
  }
};

/* BLOCO 11: submit */
document.getElementById('cadastroForm').addEventListener('submit', async function(e){
  e.preventDefault();

  const form = this;
  const btnSubmit = form.querySelector('button[type="submit"]');
  const telefoneFormatado = document.getElementById('telefone').value.trim();

  if (btnSubmit) {
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = 'Criando acesso...';
  }

  let r;
  let j;

  try {
    r = await fetch('/api/invite/cadastrar.php', {
      method: 'POST',
      body: new FormData(form)
    });
  } catch (e) {
    if (btnSubmit) {
      btnSubmit.disabled = false;
      btnSubmit.innerHTML = 'Concluir cadastro';
    }
    toast('Erro de comunicação com o servidor');
    return;
  }

  try {
    j = await r.json();
  } catch (e) {
    if (btnSubmit) {
      btnSubmit.disabled = false;
      btnSubmit.innerHTML = 'Concluir cadastro';
    }
    toast('Resposta inválida do servidor');
    return;
  }

  if (j.status !== 'ok') {
    if (btnSubmit) {
      btnSubmit.disabled = false;
      btnSubmit.innerHTML = 'Concluir cadastro';
    }
    toast(j.msg || 'Erro ao cadastrar');
    return;
  }

  const pin = escapeHtml(j.pin_provisorio || '----');
  const telefone = escapeHtml(telefoneFormatado || '');

  document.getElementById('wizard').innerHTML = `
    <div class="card-box text-center">
      <div style="font-size:48px;line-height:1;">🔐</div>

      <h4 class="fw-bold text-success mt-2 mb-2">Cadastro realizado!</h4>

      <p class="text-muted mb-3">
        Seu acesso foi criado com sucesso, mas sua conta ainda está
        <strong>aguardando ativação manual</strong>.
      </p>

      <div style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:16px;padding:16px;margin-bottom:14px;">
        <div style="font-size:13px;color:#6c757d;font-weight:600;margin-bottom:6px;">
          Seu WhatsApp de acesso
        </div>
        <div style="font-size:18px;font-weight:800;color:#212529;">
          ${telefone}
        </div>
      </div>

      <div style="background:linear-gradient(135deg,#fff7e6,#fff1c9);border:1px solid #ffe08a;border-radius:18px;padding:18px;margin-bottom:14px;">
        <div style="font-size:13px;color:#9a6b00;font-weight:700;margin-bottom:8px;">
          SUA SENHA DE ACESSO (PIN)
        </div>

        <div style="font-size:38px;letter-spacing:6px;font-weight:900;color:#cb0100;margin-bottom:10px;">
          ${pin}
        </div>

        <div style="font-size:13px;color:#7a5b00;">
          Guarde esse PIN agora. Salve, copie ou tire um print desta tela.
        </div>
      </div>

      <div class="d-grid gap-2 mb-3">
        <button type="button"
                class="btn btn-outline-dark"
                onclick="copiarPinConvite('${pin}')">
          <i class="bi bi-copy"></i>
          Copiar PIN
        </button>

        <a href="/publico/login.php" class="btn btn-success">
          <i class="bi bi-box-arrow-in-right"></i>
          Ir para o login
        </a>
      </div>

      <div style="font-size:13px;color:#6c757d;">
        Você já pode entrar no app com seu telefone e PIN, mas os convites
        só serão liberados após a ativação manual da sua conta.
      </div>
    </div>
  `;
});
</script>