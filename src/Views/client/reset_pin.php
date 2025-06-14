<?php /** @var string|null $error */ ?>
<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 flex flex-col items-center justify-center px-4 py-4 fixed inset-0 overflow-auto">
  <div class="w-full max-w-md relative z-10">
    <div class="text-center mb-8">
      <h1 class="text-3xl font-bold bg-gradient-to-r from-red-500 to-pink-500 bg-clip-text text-transparent mb-2">
        Восстановление PIN
      </h1>
    </div>
    <?php if (!empty($error)): ?>
      <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-400 p-4 rounded-2xl mb-6 animate-shake">
        <div class="flex items-center">
          <span class="material-icons-round text-red-500 mr-3">error</span>
          <p class="text-red-700 font-medium"><?= htmlspecialchars($error) ?></p>
        </div>
      </div>
    <?php endif; ?>
    <div class="bg-white rounded-3xl shadow-2xl p-8 backdrop-blur-sm">
      <form id="resetForm" action="/reset-pin" method="post" class="space-y-6">
        <div class="space-y-2">
          <label class="flex items-center text-sm font-semibold text-gray-700">
            <span class="material-icons-round mr-2 text-red-500">phone</span>
            Номер телефона
          </label>
          <div class="relative">
            <div class="absolute left-4 top-1/2 transform -translate-y-1/2 flex items-center">
              <span class="text-gray-700 font-semibold">+7</span>
            </div>
            <input id="phone" name="phone" type="tel" maxlength="10" inputmode="numeric" pattern="\d{10}" placeholder="902 923 7794" required class="w-full pl-16 pr-4 py-4 text-lg border-2 border-gray-100 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 placeholder-gray-400">
          </div>
          <button type="button" id="sendCode" class="mt-3 w-full bg-red-500 text-white py-2 rounded-2xl">Получить код</button>
        </div>
        <div id="codeBlock" class="space-y-2 hidden">
          <label class="flex items-center text-sm font-semibold text-gray-700">
            <span class="material-icons-round mr-2 text-red-500">message</span>
            Код из SMS
          </label>
          <div class="flex space-x-3 justify-center">
            <?php for ($i=0;$i<4;$i++): ?>
              <input type="tel" maxlength="1" inputmode="numeric" data-code-input class="w-14 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-2xl" />
            <?php endfor; ?>
          </div>
        </div>
        <div id="newPinBlock" class="space-y-2 opacity-50">
          <label class="flex items-center text-sm font-semibold text-gray-700">
            <span class="material-icons-round mr-2 text-red-500">lock</span>
            Новый PIN
          </label>
          <div class="flex space-x-3 justify-center">
            <?php for ($i=0;$i<4;$i++): ?>
              <input type="tel" maxlength="1" inputmode="numeric" data-pin-input class="w-14 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-2xl" disabled />
            <?php endfor; ?>
          </div>
          <input type="hidden" name="pin" id="pinHidden">
          <input type="hidden" name="code" id="codeHidden">
          <button type="submit" id="savePinBtn" class="w-full bg-gradient-to-r from-red-500 to-pink-500 text-white py-2 rounded-2xl" disabled>Сохранить PIN</button>
        </div>
      </form>
    </div>
  </div>
</main>
<script>
const phoneInput = document.getElementById('phone');
phoneInput.addEventListener('input',()=>{phoneInput.value=phoneInput.value.replace(/\D/g,'').slice(0,10);});
const sendBtn=document.getElementById('sendCode');
const codeBlock=document.getElementById('codeBlock');
const newPinBlock=document.getElementById('newPinBlock');
const codeInputs=document.querySelectorAll('input[data-code-input]');
const pinInputs=document.querySelectorAll('input[data-pin-input]');
const savePinBtn=document.getElementById('savePinBtn');
const codeHidden=document.getElementById('codeHidden');
const pinHidden=document.getElementById('pinHidden');

sendBtn.addEventListener('click',()=>{
  const phone=phoneInput.value.replace(/\D/g,'');
  if(phone.length!==10)return alert('Введите номер');
  fetch('/reset-pin/send-code',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'phone='+phone})
    .then(r=>r.json()).then(()=>{codeBlock.classList.remove('hidden');codeInputs[0].focus();});
});

function verifyResetCode(){
  const code=Array.from(codeInputs).map(i=>i.value).join('');
  if(code.length!==4)return;
  const phone=phoneInput.value.replace(/\D/g,'');
  fetch('/api/verify-reset-code',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'phone='+phone+'&code='+code})
    .then(r=>r.json()).then(d=>{
      if(d.success){
        newPinBlock.classList.remove('opacity-50');
        codeHidden.value=code;
        codeBlock.classList.add('hidden');
        pinInputs.forEach(i=>i.disabled=false);
        savePinBtn.disabled=false;
        pinInputs[0].focus();
      }else{
        alert('Неверный код');
        codeInputs.forEach(i=>i.value='');
        codeInputs[0].focus();
      }
    });
}

codeInputs.forEach((el,idx)=>{
  el.addEventListener('input',()=>{
    el.value=el.value.replace(/\D/,'').slice(0,1);
    if(el.value&&idx<codeInputs.length-1)codeInputs[idx+1].focus();
    verifyResetCode();
  });
  el.addEventListener('keydown',(e)=>{
    if(e.key==='Backspace'&&!el.value&&idx>0){codeInputs[idx-1].focus();}
  });
});

pinInputs.forEach((el,idx)=>{
  el.addEventListener('input',()=>{
    el.value=el.value.replace(/\D/,'').slice(0,1);
    if(el.value&&idx<pinInputs.length-1)pinInputs[idx+1].focus();
    updateHiddenPin();
  });
  el.addEventListener('keydown',(e)=>{
    if(e.key==='Backspace'){
      if(!el.value&&idx>0){pinInputs[idx-1].focus();pinInputs[idx-1].value='';updateHiddenPin();}
      else if(el.value){el.value='';updateHiddenPin();}
    }
  });
});

function updateHiddenPin(){
  pinHidden.value=Array.from(pinInputs).map(i=>i.value).join('');
}
</script>
