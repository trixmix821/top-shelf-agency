(function(){
  "use strict";
  var config=window.TOP_SHELF_CONFIG||{};
  var root=document.documentElement;
  var menu=document.getElementById("mobileMenu");
  var menuButton=document.getElementById("navToggle");
  var themeButton=document.getElementById("themeToggle");

  function updateThemeLabel(){
    if(themeButton){
      themeButton.setAttribute(
        "aria-label",
        root.getAttribute("data-theme")==="dark"?"Switch to light mode":"Switch to dark mode"
      );
    }
  }

  if(root.getAttribute("data-theme")!=="light")root.setAttribute("data-theme","dark");
  updateThemeLabel();

  if(themeButton){
    themeButton.addEventListener("click",function(){
      var next=root.getAttribute("data-theme")==="dark"?"light":"dark";
      root.setAttribute("data-theme",next);
      try{
        localStorage.setItem("top-shelf-theme",next);
        localStorage.removeItem("theme");
      }catch(error){}
      updateThemeLabel();
    });
  }

  if(menuButton&&menu){
    menuButton.addEventListener("click",function(){
      var open=menu.classList.toggle("open");
      menuButton.setAttribute("aria-expanded",String(open));
      menuButton.setAttribute("aria-label",open?"Close menu":"Open menu");
    });
  }
  if(menu){
    menu.querySelectorAll("a").forEach(function(link){
      link.addEventListener("click",function(){
        menu.classList.remove("open");
        if(menuButton)menuButton.setAttribute("aria-expanded","false");
      });
    });
  }

  function track(name){
    dispatchEvent(new CustomEvent("topshelf:conversion",{detail:{name:name,path:location.pathname}}));
  }
  document.querySelectorAll("[data-event]").forEach(function(element){
    element.addEventListener("click",function(){track(element.dataset.event);});
  });

  var keys=["utm_source","utm_medium","utm_campaign","utm_content","utm_term"];
  var query=new URLSearchParams(location.search);
  try{
    keys.forEach(function(key){
      if(query.get(key))localStorage.setItem("tsa_"+key,query.get(key));
    });
    if(!localStorage.getItem("tsa_first"))localStorage.setItem("tsa_first",location.href);
  }catch(error){}

  var form=document.getElementById("growthAuditForm");
  if(!form)return;

  var apiEndpoint="/api/growth-audit";
  var status=document.getElementById("formStatus");
  var started=false;
  var submissionToken="";
  var tokenReceivedAt=0;

  function storedValue(name){
    try{return localStorage.getItem(name)||"";}catch(error){return "";}
  }
  keys.forEach(function(key){form.elements[key].value=storedValue("tsa_"+key);});
  form.elements.landing_page.value=location.href;
  form.elements.first_touch_url.value=storedValue("tsa_first")||location.href;
  if(config.consentCopy)document.getElementById("consentCopy").textContent=config.consentCopy;

  async function refreshSubmissionToken(){
    var response=await fetch(apiEndpoint,{
      method:"GET",
      headers:{"Accept":"application/json"},
      credentials:"same-origin",
      cache:"no-store"
    });
    var data=await response.json().catch(function(){return {};});
    if(!response.ok||!data.token)throw new Error("The form could not be verified.");
    submissionToken=data.token;
    tokenReceivedAt=Date.now();
    return submissionToken;
  }

  function wait(milliseconds){
    return new Promise(function(resolve){setTimeout(resolve,milliseconds);});
  }

  function clearFieldErrors(){
    form.querySelectorAll('[aria-invalid="true"]').forEach(function(field){
      field.removeAttribute("aria-invalid");
    });
  }

  function markFieldErrors(fields){
    var first=null;
    if(!fields||typeof fields!=="object")return;
    Object.keys(fields).forEach(function(name){
      var field=form.elements[name];
      if(field&&field.setAttribute){
        field.setAttribute("aria-invalid","true");
        if(!first)first=field;
      }
    });
    if(first&&first.focus)first.focus();
  }

  refreshSubmissionToken().catch(function(){});

  form.addEventListener("input",function(event){
    if(event.target&&event.target.removeAttribute)event.target.removeAttribute("aria-invalid");
    if(!started){track("audit_form_start");started=true;}
  });

  form.addEventListener("submit",async function(event){
    event.preventDefault();
    clearFieldErrors();
    status.className="status";
    status.textContent="";
    if(!form.checkValidity()){
      form.reportValidity();
      status.textContent="Please complete the required fields.";
      status.classList.add("error");
      return;
    }
    if(form.elements.company_fax.value)return;

    var button=form.querySelector('button[type="submit"]');
    button.disabled=true;
    button.textContent="Sending...";
    try{
      if(!submissionToken)await refreshSubmissionToken();
      var tokenAge=Date.now()-tokenReceivedAt;
      if(tokenAge<2100)await wait(2100-tokenAge);

      var payload=Object.fromEntries(new FormData(form));
      payload.submission_token=submissionToken;
      var response=await fetch(apiEndpoint,{
        method:"POST",
        headers:{"Accept":"application/json","Content-Type":"application/json"},
        credentials:"same-origin",
        body:JSON.stringify(payload)
      });
      var data=await response.json().catch(function(){return {};});
      if(!response.ok||!data.ok){
        var error=new Error(data.message||"We could not send your request. Try again or email hello@topshelfagency.biz.");
        error.status=response.status;
        error.fields=data.fields||{};
        throw error;
      }
      track("audit_form_completion");
      location.assign(data.redirect||config.successUrl||"/thank-you/");
    }catch(error){
      if(error.status===403){
        submissionToken="";
        refreshSubmissionToken().catch(function(){});
      }
      markFieldErrors(error.fields);
      status.textContent=error.message||"We could not send your request. Try again or email hello@topshelfagency.biz.";
      status.classList.add("error");
      button.disabled=false;
      button.textContent="Get A Free Audit";
    }
  });
}());
