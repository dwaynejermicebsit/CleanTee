document.querySelectorAll('input[name="phone"]').forEach(input=>{
 input.type='tel';input.maxLength=11;input.minLength=11;input.inputMode='numeric';input.pattern='09[0-9]{9}';input.placeholder='09XXXXXXXXX';input.title='Enter exactly 11 digits starting with 09.';
 input.addEventListener('input',()=>{input.value=input.value.replace(/\D/g,'').slice(0,11);input.setCustomValidity(input.value&&!/^09\d{9}$/.test(input.value)?'Enter an 11-digit Philippine mobile number starting with 09.':'')});
});
/* Safety net: any page with a date field but without js/pickers.js (e.g. report) loads it automatically. */
let ctInjecting=false;
if(document.querySelector('input[type="date"]')&&!document.querySelector('script[src*="pickers.js"]')){
 ctInjecting=true;const s=document.createElement('script');
 s.src=(document.currentScript&&document.currentScript.src?document.currentScript.src:'js/enhancements.js').replace(/enhancements\.js.*$/,'pickers.js');
 document.head.append(s);
}
document.querySelectorAll(ctInjecting?'input[type="time"]':'input[type="date"],input[type="time"]').forEach(input=>{
 const note=document.createElement('small');note.className='selection-note';note.setAttribute('aria-live','polite');input.after(note);
 input.addEventListener('change',()=>{note.textContent=input.value?'Selected: '+input.value:'';input.classList.remove('schedule-picked');void input.offsetWidth;input.classList.add('schedule-picked')});
 input.form?.addEventListener('reset',()=>{note.textContent='';input.classList.remove('schedule-picked')});
});
document.addEventListener('click',e=>{const b=e.target.closest('.btn,.cta');if(!b||matchMedia('(prefers-reduced-motion: reduce)').matches)return;const r=b.getBoundingClientRect(),dot=document.createElement('span');dot.className='ripple';dot.style.left=(e.clientX-r.left-6)+'px';dot.style.top=(e.clientY-r.top-6)+'px';b.append(dot);setTimeout(()=>dot.remove(),650)});
document.querySelectorAll('.ph,.tabs,.stats').forEach(e=>e.classList.add('soft-reveal'));