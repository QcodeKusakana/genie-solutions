(() => {
  'use strict';
  const deleteForm = document.getElementById('quote-delete-form');
  const deleteDialog = document.getElementById('quote-delete-dialog');
  if (deleteForm && deleteDialog) {
    deleteForm.addEventListener('submit', event => {
      if (deleteForm.dataset.confirmed === 'true') return;
      event.preventDefault();
      if (typeof deleteDialog.showModal === 'function') deleteDialog.showModal();
      else if (window.confirm('Supprimer définitivement cette demande et ses informations associées ?')) {
        deleteForm.dataset.confirmed = 'true';
        deleteForm.querySelector('[name="confirmed"]').value = '1';
        deleteForm.requestSubmit();
      }
    });
    deleteDialog.addEventListener('close', () => {
      if (deleteDialog.returnValue !== 'confirm') return;
      deleteForm.dataset.confirmed = 'true';
      deleteForm.querySelector('[name="confirmed"]').value = '1';
      deleteForm.requestSubmit();
    });
  }

  const labels = {
    partner_save: ['Ajouter un partenaire', 'Modifier le partenaire'],
    faq_save: ['Ajouter une question', 'Modifier la question'],
    service_save: ['Ajouter un service', 'Modifier le service'],
    project_save: ['Ajouter une réalisation', 'Modifier la réalisation'],
    product_save: ['Ajouter un produit', 'Modifier le produit'],
    admin_user_create: ['Ajouter un compte', 'Ajouter un compte'],
    home_section_save: ['Modifier cette section', 'Modifier cette section'],
    page_save: ['Modifier cette page', 'Modifier cette page'],
    media_upload: ['Ajouter une image', 'Ajouter une image']
  };
  const ids = {partner_save:'partner_id',faq_save:'faq_id',service_save:'service_id',project_save:'project_id',product_save:'product_id',page_save:'page_id'};
  document.querySelectorAll('form.admin-modal-form').forEach((form,index) => {
    const action=form.querySelector('input[name="action"]')?.value||'';
    const idField=ids[action];
    const editing=action==='page_save'||(idField&&Number(form.querySelector('[name="'+idField+'"]')?.value)>0);
    const panel=form.closest('.admin-panel');
    const title=(panel?.querySelector('.panel-heading h2')?.textContent||'').trim();
    const [createLabel,editLabel]=labels[action]||['Ouvrir le formulaire','Modifier'];
    const id='admin-form-dialog-'+(index+1);
    const dialog=document.createElement('dialog');
    dialog.className='admin-form-dialog';dialog.id=id;dialog.setAttribute('aria-labelledby',id+'-title');
    const head=document.createElement('div');head.className='admin-form-dialog-head';
    const heading=document.createElement('h2');heading.id=id+'-title';heading.textContent=title||editLabel;
    const close=document.createElement('button');close.type='button';close.className='admin-form-dialog-close';close.setAttribute('aria-label','Fermer le formulaire');close.innerHTML='<i class="bi bi-x-lg" aria-hidden="true"></i>';close.addEventListener('click',()=>dialog.close());
    head.append(heading,close);
    const body=document.createElement('div');body.className='admin-form-dialog-body';body.append(form);dialog.append(head,body);document.body.append(dialog);
    const trigger=document.createElement('button');trigger.type='button';trigger.className='btn btn-admin-primary admin-modal-trigger';trigger.setAttribute('aria-haspopup','dialog');trigger.setAttribute('aria-controls',id);trigger.innerHTML='<i class="bi '+(editing?'bi-pencil-square':'bi-plus-lg')+'" aria-hidden="true"></i> '+(editing?editLabel:createLabel);trigger.addEventListener('click',()=>dialog.showModal());
    const headingRow=panel?.querySelector('.panel-heading');if(headingRow)headingRow.append(trigger);else form.before(trigger);
    dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close()});
    if(new URLSearchParams(location.search).has('edit')&&action!=='home_section_save')dialog.showModal();
  });
})();
