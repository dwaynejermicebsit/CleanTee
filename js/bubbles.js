/* CLEANTEE bubbles: adds floating bubbles + sparkles to page banners and a soft layer behind every page. */
(function(){
  function rnd(a,b){return a+Math.random()*(b-a)}
  function layer(cls,nB,nS,bMin,bMax){
    var d=document.createElement('div');d.className='ct-fx '+cls;d.setAttribute('aria-hidden','true');
    for(var i=0;i<nB;i++){var b=document.createElement('i');b.className='ct-b';var s=rnd(bMin,bMax);
      b.style.cssText='width:'+s+'px;height:'+s+'px;left:'+rnd(2,96)+'%;animation-duration:'+rnd(9,20)+'s;animation-delay:-'+rnd(0,18)+'s';d.appendChild(b)}
    for(var j=0;j<nS;j++){var t=document.createElement('i');t.className='ct-s';
      t.style.cssText='--s:'+rnd(10,20)+'px;left:'+rnd(2,96)+'%;top:'+rnd(5,90)+'%;animation-duration:'+rnd(3,6)+'s;animation-delay:-'+rnd(0,6)+'s';d.appendChild(t)}
    return d}
  function init(){
    if(document.querySelector('.ct-fx'))return;
    var banners=document.querySelectorAll('.ph');
    banners.forEach(function(el){el.insertBefore(layer('',9,6,10,34),el.firstChild)});
    var w=Math.max(innerWidth,320),n=w<700?7:12;
    document.body.insertBefore(layer('page',n,8,16,48),document.body.firstChild);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();