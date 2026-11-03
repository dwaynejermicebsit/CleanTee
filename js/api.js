/* All account, booking and report data comes from the PHP backend. */
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
let ctUser=null,ctCsrf='',ctAuthError=null;
const SESSION=()=>ctUser;
async function readResponse(r){
 let d;try{d=await r.json()}catch(e){throw Error('The PHP server returned an invalid response. Open the site through http://localhost, and check the backend folder and Apache/PHP logs.');}
 if(!r.ok||!d.ok){const e=Error(d.message||'Request failed');e.code=d.error;e.status=r.status;throw e;}return d;
}
async function refreshSession(){
 const d=await readResponse(await fetch('backend/auth/session.php',{credentials:'same-origin',cache:'no-store'}));
 ctUser=d.user;ctCsrf=d.csrf;ctAuthError=null;return ctUser;
}
const authReady=refreshSession().catch(e=>{ctAuthError=e;return null}).finally(()=>document.dispatchEvent(new Event('ct-auth-ready')));
async function authRequest(action,form){
 await authReady;if(!ctCsrf)await refreshSession();
 const send=()=>fetch('backend/auth/'+action+'.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':ctCsrf},body:form||new FormData()}).then(readResponse);
 let d;try{d=await send()}catch(e){if(e.code!=='csrf')throw e;await refreshSession();d=await send();}
 if(action==='logout'){ctUser=null;ctCsrf='';sessionStorage.removeItem('ct_admin');localStorage.removeItem('ct_session');}
 else if(d.user){ctUser=d.user;ctCsrf=d.csrf;}return d;
}
async function api(key,body,method='POST'){
 if(['login','register'].includes(key))return authRequest(key,body);
 await authReady;if(ctAuthError)throw ctAuthError;
 if(!ctUser)throw Error('Please log in before continuing.');
 const endpoint=key==='booking'?'bookings/'+(method==='GET'?'mine.php':'create.php'):key==='report'?'reports/create.php':null;
 if(!endpoint)throw Error('Unknown request');
 const r=await fetch('backend/'+endpoint,{method,credentials:'same-origin',headers:method==='POST'?{'X-CSRF-Token':ctCsrf}:{},...(method==='POST'?{body}:{})});
 const d=await readResponse(r);return key==='booking'&&method==='GET'?d.bookings:d;
}
