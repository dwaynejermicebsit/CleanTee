/* Minimal service worker: caches static assets, never caches the backend or admin pages. */
const V='cleantee-v1',ASSETS=['css/style.css','css/enhancements.css','js/config.js','images/logo.png'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(V).then(c=>c.addAll(ASSETS)).catch(()=>{}));self.skipWaiting()});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!==V).map(x=>caches.delete(x)))))});
self.addEventListener('fetch',e=>{
 const u=new URL(e.request.url);
 if(e.request.method!=='GET'||u.origin!==location.origin||u.pathname.includes('/backend/')||u.pathname.includes('admin'))return;
 e.respondWith(fetch(e.request).then(r=>{if(r.ok&&/\.(css|js|png|jpg|webp)$/.test(u.pathname)){const c=r.clone();caches.open(V).then(x=>x.put(e.request,c))}return r}).catch(()=>caches.match(e.request)));
});
