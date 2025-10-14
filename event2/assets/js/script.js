// EVENT2 - JS minimal
document.addEventListener('DOMContentLoaded',()=>{
  const toggles = document.querySelectorAll('.toggle-password');
  toggles.forEach(el=>{
    el.addEventListener('click',()=>{
      const input = document.querySelector(el.dataset.toggle);
      if(input.type==='password') {
        input.type='text';
        el.textContent=' ';
      } else {
        input.type='password';
        el.textContent='👁️';
      }
    });
  });
});
