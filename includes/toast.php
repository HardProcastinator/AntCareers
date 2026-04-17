<!-- ═══ AntCareers Unified Toast System ═══ -->
<div id="ac-toast-container"></div>
<style>
#ac-toast-container{position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column-reverse;gap:10px;pointer-events:none;}
.ac-toast{pointer-events:auto;display:flex;align-items:center;gap:10px;padding:12px 16px 12px 14px;min-width:280px;max-width:400px;border-radius:10px;font-size:13px;font-weight:600;font-family:var(--font-body,'Plus Jakarta Sans',sans-serif);line-height:1.4;background:#1E1616;color:#F5F0EE;border:1px solid rgba(255,255,255,0.08);border-left:4px solid #2563EB;box-shadow:0 10px 36px rgba(0,0,0,0.5);animation:acToastIn .3s ease;}
.ac-toast--success{border-left:4px solid #4CAF70!important;}
.ac-toast--success .ac-toast-icon{color:#4CAF70;}
.ac-toast--error{border-left:4px solid #E05555!important;}
.ac-toast--error .ac-toast-icon{color:#E05555;}
.ac-toast--warning{border-left:4px solid #F5A623!important;}
.ac-toast--warning .ac-toast-icon{color:#F5A623;}
.ac-toast--info{border-left:4px solid #2563EB!important;}
.ac-toast--info .ac-toast-icon{color:#2563EB;}
.ac-toast-icon{font-size:16px;flex-shrink:0;}
.ac-toast-msg{flex:1;}
.ac-toast-close{margin-left:auto;background:none;border:none;color:inherit;opacity:.45;cursor:pointer;font-size:16px;padding:2px 4px;line-height:1;flex-shrink:0;transition:opacity .15s;}
.ac-toast-close:hover{opacity:1;}
.ac-toast.leaving{animation:acToastOut .25s ease forwards;}
body.light .ac-toast{background:#FFFFFF;color:#1A0A09;border-top-color:#E0CECA;border-right-color:#E0CECA;border-bottom-color:#E0CECA;box-shadow:0 10px 36px rgba(0,0,0,0.12);}
body.light .ac-toast-close{color:#5A4040;}
@keyframes acToastIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
@keyframes acToastOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(40px)}}
</style>
<script>
(function(){
  function _showToast(msg,typeOrIcon,extra){
    var type='info',icon='fa-info-circle';
    if(typeOrIcon==='success'||typeOrIcon==='ok'){type='success';icon='fa-check-circle';}
    else if(typeOrIcon==='error'||typeOrIcon==='err'){type='error';icon='fa-exclamation-circle';}
    else if(typeOrIcon==='warning'||extra===true){type='warning';icon='fa-exclamation-triangle';}
    else if(typeOrIcon==='info'){type='info';icon='fa-info-circle';}
    else if(typeOrIcon&&typeOrIcon.indexOf&&typeOrIcon.indexOf('fa-')===0){
      icon=typeOrIcon;
      if(/check|heart$/.test(icon))type='success';
      else if(/exclamation|times|trash|ban|heart-broken|sign-out/.test(icon))type='error';
      else if(/warning|triangle/.test(icon))type='warning';
      else type='info';
    }
    var container=document.getElementById('ac-toast-container');
    if(!container){container=document.createElement('div');container.id='ac-toast-container';document.body.appendChild(container);}
    var el=document.createElement('div');
    el.className='ac-toast ac-toast--'+type;
    el.innerHTML='<i class="fas '+icon+' ac-toast-icon"></i><span class="ac-toast-msg">'+msg+'</span><button class="ac-toast-close" aria-label="Close">&times;</button>';
    container.appendChild(el);
    var timer=setTimeout(function(){
      el.classList.add('leaving');
      setTimeout(function(){if(el.parentElement)el.remove();},300);
    },4000);
    el.querySelector('.ac-toast-close').addEventListener('click',function(){clearTimeout(timer);el.remove();});
  }
  window.showToast=_showToast;
  window.toast=function(msg,type){_showToast(msg,type);};
})();
</script>
