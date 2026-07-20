(function(){
  "use strict";
  var config=window.TOP_SHELF_CONFIG||{};
  var root=document.documentElement;
  var menu=document.getElementById("mobileMenu");
  var menuButton=document.getElementById("navToggle");
  var themeButton=document.getElementById("themeToggle");
  function updateThemeLabel(){if(themeButton)themeButton.setAttribute("aria-label",root.getAttribute("data-theme")==="dark"?"Switch to light mode":"Switch to dark mode")}
  updateThemeLabel();
  if(themeButton)themeButton.addEventListener("click",function(){var next=root.getAttribute("data-theme")==="dark"?"light":"dark";root.setAttribute("data-theme",next);try{localStorage.setItem("theme",next)}catch(e){}updateThemeLabel()});
  if(menuButton&&menu)menuButton.addEventListener("click",function(){var open=menu.classList.toggle("open");menuButton.setAttribute("aria-expanded",String(open));menuButton.setAttribute("aria-label",open?"Close menu":"Open menu")});
  if(menu)menu.querySelectorAll("a").forEach(function(link){link.addEventListener("click",function(){menu.classList.remove("open");menuButton.setAttribute("aria-expanded","false")})});
  function track(name){dispatchEvent(new CustomEvent("topshelf:conversion",{detail:{name:name,path:location.pathname}}))}
  document.querySelectorAll("[data-event]").forEach(function(el){el.addEventListener("click",function(){track(el.dataset.event)})});
  var keys=["utm_source","utm_medium","utm_campaign","utm_content","utm_term"];
  var query=new URLSearchParams(location.search);
  keys.forEach(function(key){if(query.get(key))localStorage.setItem("tsa_"+key,query.get(key))});
  if(!localStorage.getItem("tsa_first"))localStorage.setItem("tsa_first",location.href);
  var form=document.getElementById("growthAuditForm");
  if(!form)return;
  var status=document.getElementById("formStatus"),started=false;
  keys.forEach(function(key){form.elements[key].value=localStorage.getItem("tsa_"+key)||""});
  form.elements.landing_page.value=location.href;
  form.elements.first_touch_url.value=localStorage.getItem("tsa_first");
  if(config.consentCopy)document.getElementById("consentCopy").textContent=config.consentCopy;
  form.addEventListener("input",function(){if(!started){track("audit_form_start");started=true}});
  form.addEventListener("submit",async function(event){
    event.preventDefault();status.className="status";
    if(!form.checkValidity()){form.reportValidity();status.textContent="Please complete the required fields.";status.classList.add("error");return}
    if(form.elements.company_fax.value)return;
    if(!config.ghlFormEndpoint){status.textContent="This form is awaiting its verified GoHighLevel connection. Please email hello@topshelfagency.biz for now.";status.classList.add("error");return}
    var button=form.querySelector('button[type="submit"]');button.disabled=true;button.textContent="Sending…";
    try{var payload=Object.fromEntries(new FormData(form));delete payload.company_fax;var response=await fetch(config.ghlFormEndpoint,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)});if(!response.ok)throw new Error("Request failed");track("audit_form_completion");location.assign(config.successUrl||"/thank-you/")}
    catch(error){status.textContent="We could not send your request. Try again or email hello@topshelfagency.biz.";status.classList.add("error");button.disabled=false;button.textContent="Get My Free Growth Audit"}
  });
}());
