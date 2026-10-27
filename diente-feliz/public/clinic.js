document.querySelector('.menu-toggle')?.addEventListener('click',function(){document.body.classList.toggle('menu-open');this.setAttribute('aria-expanded',String(document.body.classList.contains('menu-open')));});
document.querySelector('.select-all')?.addEventListener('change',function(){document.querySelectorAll('input[name="ids[]"]').forEach(item=>item.checked=this.checked);});
document.querySelectorAll('.copy-template').forEach(button=>button.addEventListener('click',()=>{document.querySelector('textarea[name="body"]').value=button.dataset.template;}));
document.querySelectorAll('.print-button').forEach(button=>button.addEventListener('click',()=>window.print()));
