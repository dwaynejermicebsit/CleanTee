/* CLEANTEE booking page script (moved out of book.html). Load AFTER config.js, data.js, api.js, layout.js, main.js. */
console.info('[CLEANTEE] booking.js v20261027a loaded');
(() => {
  const $id = id => document.getElementById(id);

  const form = $id("bookingForm");
  const modal = $id("bookingModal");
  const serviceInput = $id("bs");
  const submitButton = form.querySelector(
    'button[type="submit"]'
  );

  const transport = {
    "Pateros": [250, 250],
    "Pasig": [150, 250],
    "Pasay": [150, 150],
    "Mandaluyong": [150, 150],
    "South Caloocan": [150, 200],
    "North Caloocan": [250, 300],
    "Valenzuela": [200, 200],
    "San Juan": [150, 150],
    "Marikina": [175, 175],
    "Quezon City": [150, 200]
  };

  const propertyOptions = {
    residential: [
      "House",
      "Condominium Unit",
      "Apartment",
      "Townhouse",
      "Other Residential Property"
    ],
    commercial: [
      "Office",
      "Retail Store",
      "Restaurant",
      "Commercial Building",
      "Other Commercial Space"
    ],
    deep: [
      "House",
      "Condominium Unit",
      "Apartment",
      "Office",
      "Commercial Space",
      "Other"
    ],
    post: [
      "House",
      "Condominium Unit",
      "Apartment",
      "Office",
      "Commercial Space",
      "Other"
    ],
    move: [
      "House",
      "Condominium Unit",
      "Apartment",
      "Townhouse",
      "Office",
      "Other"
    ],
    fogging: [
      "House",
      "Condominium Unit",
      "Apartment",
      "Office",
      "Commercial Space",
      "Other"
    ],
    grease: [
      "Restaurant",
      "Commercial Kitchen",
      "Residential Kitchen",
      "Other"
    ],
    aircon: []
  };

  const money = n =>
    "₱" + Number(n).toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });

  const show = (id, visible) => {
    $id(id).hidden = !visible;
  };

  const getService = () =>
    SERVICES.find(s => s.id === current) || SERVICES[0];

  const getCleaners = () =>
    current === "aircon"
      ? 2
      : Math.max(
          1,
          Math.min(20, Number($id("cleaners").value) || 1)
        );

  let current = "residential";
  let previousFocus = null;
  let notificationTimer;

  // UPDATE CONDITIONAL FORM FIELDS
  function updateFields() {
    const s = getService();
    const air = current === "aircon";
    const grease = current === "grease";

    $id("selectedService").textContent =
      "Selected Service: " + s.name;

    show("propertyWrap", !air);
    show("sqmWrap", !air && !grease);
    show("floorsWrap", !air && !grease);

    show(
      "greaseWrap",
      ["commercial", "deep", "post", "grease"]
        .includes(current)
    );

    show("cleanersWrap", true);
    show("hoursWrap", !air && !grease && !s.flat);
    show("airconTypeWrap", air);
    show("airconQtyWrap", air);
    show("parkingWrap", air);

    // Aircon services have two cleaners
    const cleanersInput = $id("cleaners");

    if (air) {
      cleanersInput.value = 2;
      cleanersInput.readOnly = true;
    } else {
      cleanersInput.readOnly = false;
    }

    // Property options
    const property = $id("propertyType");
    const oldValue = property.value;

    property.replaceChildren(
      new Option("Select property type", "")
    );

    (propertyOptions[current] || []).forEach(name => {
      property.add(new Option(name, name));
    });

    if ((propertyOptions[current] || []).includes(oldValue)) {
      property.value = oldValue;
    }

    property.required = !air;
    $id("sqm").required = !air && !grease;
    $id("floors").required = !air && !grease;

    $id("bh").required = !air && !grease && !s.flat;
    $id("bh").min = s.min || 1;

    if (+$id("bh").value < (s.min || 1)) {
      $id("bh").value = s.min || 1;
    }

    $id("airconType").required = air;
    $id("airconQty").required = air;
    $id("greaseQty").required = grease;

    if (grease) {
      $id("greaseQty").min = 1;

      if (+$id("greaseQty").value < 1) {
        $id("greaseQty").value = 1;
      }
    } else {
      $id("greaseQty").min = 0;
    }

    updateCondo();
    updateReceipt();
  }

  // CONDOMINIUM UNIT NUMBER
  function updateCondo() {
    const condo =
      $id("propertyType").value === "Condominium Unit";

    show("unitWrap", condo);
    $id("unitNumber").required = condo;
  }

  // RECEIPT ROW
  function addRow(label, value) {
    const row = document.createElement("div");
    row.className = "receipt-line";

    const left = document.createElement("span");
    const right = document.createElement("span");

    left.textContent = label;
    right.textContent = value;

    row.append(left, right);
    $id("receiptRows").append(row);
  }

  // UPDATE RECEIPT
  function updateReceipt() {
    const s = getService();
    const air = current === "aircon";
    const grease = current === "grease";

    const cleaners = getCleaners();

    const hours = Math.max(
      s.min || 1,
      Number($id("bh").value) || 1
    );

    const city = $id("bookingCity").value;
    const cityRange = transport[city];

    const airconType = $id("airconType").value;

    const airconQty = Math.max(
      1,
      Number($id("airconQty").value) || 1
    );

    const greaseQty = Math.max(
      1,
      Number($id("greaseQty").value) || 1
    );

    const airRates = {
      window: 750,
      split: 1250,
      checkup: 499,
      repair: 0
    };

    // CLEANING: rate x hours x cleaners
    // AIRCON: rate x units
    // GREASE: rate x traps
    let serviceCost;

    if (air) {
      serviceCost = airRates[airconType] * airconQty;
    } else if (grease) {
      serviceCost = 499 * greaseQty;
    } else if (s.flat) {
      serviceCost = s.price;
    } else {
      serviceCost = s.price * hours * cleaners;
    }

    // TRANSPORTATION
    let transportMin = null;
    let transportMax = null;

    if (cityRange) {
      transportMin = air
        ? Math.max(250, cityRange[0])
        : cityRange[0];

      transportMax = air
        ? Math.max(250, cityRange[1])
        : cityRange[1];
    }

    const parking = air
      ? Number($id("parking").value)
      : 0;

    const totalMin =
      serviceCost + (transportMin || 0) + parking;

    const totalMax =
      serviceCost + (transportMax || 0) + parking;

    // REBUILD RECEIPT
    $id("receiptRows").replaceChildren();

    addRow("Service", s.name);
    addRow(
      "Number of Cleaners Hired",
      cleaners + (cleaners === 1 ? " cleaner" : " cleaners")
    );

    if ($id("bookingDate").value) {
      addRow("Date", $id("bookingDate").value);
    }

    if ($id("bookingTime").value) {
      addRow("Time", $id("bookingTime").value);
    }

    if ($id("bookingName").value) {
      addRow("Client", $id("bookingName").value);
    }

    if ($id("bookingPhone").value) {
      addRow("Contact Number", $id("bookingPhone").value);
    }

    if (city) {
      addRow("City", city);
    }

    if ($id("bookingAddress").value) {
      addRow("Location", $id("bookingAddress").value);
    }

    if (!air && $id("propertyType").value) {
      addRow("Property Type", $id("propertyType").value);
    }

    if (
      !$id("unitWrap").hidden &&
      $id("unitNumber").value
    ) {
      addRow("Unit Number", $id("unitNumber").value);
    }

    if (!air && !grease) {
      if ($id("sqm").value) {
        addRow("Floor Area", $id("sqm").value + " sqm");
      }

      if ($id("floors").value) {
        addRow("Number of Floors", $id("floors").value);
      }
    }

    // HOURLY CLEANING BREAKDOWN
    if (!air && !grease && !s.flat) {
      addRow(
        "Cleaning Fee",
        money(s.price) +
        " × " + hours + " hr" +
        " × " + cleaners + " cleaner(s)" +
        " = " + money(serviceCost)
      );
    }

    // FIXED-PRICE SERVICES
    if (!air && !grease && s.flat) {
      addRow("Service Fee", money(serviceCost));
    }

    // GREASE TRAP
    if (grease) {
      addRow(
        "Grease Trap Cleaning",
        money(499) +
        " × " + greaseQty +
        " trap(s) = " + money(serviceCost)
      );
    }

    if (
      !grease &&
      !air &&
      !$id("greaseWrap").hidden &&
      Number($id("greaseQty").value) > 0
    ) {
      addRow(
        "Grease Traps",
        $id("greaseQty").value +
        " — additional charges to be confirmed"
      );
    }

    // AIRCON
    if (air) {
      const airNames = {
        window: "Window-Type Cleaning",
        split: "Split-Type Cleaning",
        checkup: "Aircon Checkup",
        repair: "Aircon Repair"
      };

      addRow(
        airNames[airconType],
        airconType === "repair"
          ? "Price upon assessment"
          : money(airRates[airconType]) +
            " × " + airconQty +
            " unit(s) = " + money(serviceCost) +
            (airconType === "checkup" ? " (starting rate)" : "")
      );
    }

    // TRANSPORTATION ROW
    addRow(
      "Transportation Fee",
      !cityRange
        ? "To be confirmed"
        : transportMin === transportMax
          ? money(transportMin)
          : money(transportMin) + "–" + money(transportMax)
    );

    if (air) {
      addRow(
        "Motorcycle Parking",
        parking ? money(parking) : "Not included"
      );
    }

    // TOTAL
    if (!cityRange) {
      $id("receiptTotal").textContent =
        "To be confirmed";
    } else if (airconType === "repair" && air) {
      $id("receiptTotal").textContent =
        "Upon assessment";
    } else if (airconType === "checkup" && air) {
      $id("receiptTotal").textContent =
        "From " + money(totalMin);
    } else if (totalMin === totalMax) {
      $id("receiptTotal").textContent =
        money(totalMin);
    } else {
      $id("receiptTotal").textContent =
        money(totalMin) + "–" + money(totalMax);
    }

    // BOOKING NOTES
    const notes = [];

    if (!air) {
      notes.push(
        "Additional cleaning time, if required, " +
        "is ₱250 per hour per cleaner. " +
        "Extra hours must be communicated and " +
        "approved in advance by both the client " +
        "and CLEANTEE to avoid confusion."
      );
    }

    if (air) {
      notes.push(
        "Aircon services include a two-person team. " +
        "Transportation starts at ₱250. " +
        "Motorcycle parking may cost approximately ₱50."
      );

      notes.push(
        "Repair rates vary depending on assessment. " +
        "The checkup fee is waived when the client " +
        "proceeds with the repair."
      );
    }

    if (!cityRange) {
      notes.push(
        "Transportation for this location requires confirmation."
      );
    } else if (transportMin !== transportMax) {
      notes.push(
        "Transportation is estimated as a range. " +
        "The final transportation charge will be confirmed."
      );
    }

    notes.push(
      "This is an estimated quotation. " +
      "Final charges and appointment availability " +
      "are subject to CLEANTEE confirmation."
    );

    $id("receiptNotes").textContent = notes.join(" ");

    return {
      cleaners,
      serviceCost,
      transportMin,
      transportMax,
      parking,
      totalMin,
      totalMax
    };
  }

  // OPEN BOOKING MODAL
  async function openBooking(serviceId = "") {
    await authReady;
    // Never display the booking form to a guest.
    if (typeof SESSION !== "function" || !SESSION()) {
      const dest = new URL("services.html", location.href);
      dest.searchParams.set("book", "1");
      if (serviceId) dest.searchParams.set("service", serviceId);
      sessionStorage.setItem("ct_booking_return", dest.pathname.split("/").pop() + dest.search);
      try { sessionStorage.setItem("ct_booking_resume", "1"); } catch (e) {}
      location.href = "auth.html";
      return;
    }
    previousFocus = document.activeElement;

    const selected = SERVICES.find(
      s => s.id === serviceId
    );

    current = selected
      ? selected.id
      : SERVICES[0].id;

    form.reset();
    next.value = current;
    paymentHelp();
    drawBasket();

    serviceInput.value = current;
    $id("cleaners").value = 1;
    $id("bh").value = 3;
    $id("airconQty").value = 1;
    $id("greaseQty").value = 0;

    const now = new Date();

    // Bookings need at least 24 hours notice, so the earliest pickable date is the day after "now + 24 hours" starts.
    $id("bookingDate").min =
      new Intl.DateTimeFormat("en-CA", {timeZone:"Asia/Manila",year:"numeric",month:"2-digit",day:"2-digit"}).format(new Date(now.getTime()+24*3600*1000));
    checkNotice();

    if (typeof SESSION === "function") {
      const user = SESSION();

      if (user) {
        form.elements.name.value = user.name || "";
        form.elements.email.value = user.email || "";
        form.elements.phone.value = user.phone || "";
      }
    }

    updateFields();

    modal.hidden = false;
    document.body.classList.add("booking-open");

    $id("bookingClose").focus();
  }

  // CLOSE BOOKING MODAL
  function closeBooking() {
    modal.hidden = true;
    document.body.classList.remove("booking-open");

    if (previousFocus?.isConnected) {
      previousFocus.focus();
    }
  }

  // OPEN FROM SERVICE CARDS
  document.addEventListener("click", event => {
    const trigger = event.target.closest(
      '[data-book-service], ' +
      '[data-open-booking], ' +
      'a[href*="book.html"]'
    );

    if (!trigger) return;

    event.preventDefault();

    let serviceId = trigger.dataset.bookService || "";

    if (!serviceId && trigger.hasAttribute("href")) {
      const url = new URL(trigger.href, location.href);
      serviceId = url.searchParams.get("service") || "";
    }

    openBooking(serviceId);
  });

  // CLOSE BUTTON
  $id("bookingClose").addEventListener(
    "click", closeBooking
  );

  // CLICK OUTSIDE MODAL
  modal.addEventListener("click", event => {
    if (event.target === modal) {
      closeBooking();
    }
  });

  // ESCAPE KEY
  document.addEventListener("keydown", event => {
    if (event.key === "Escape" && !modal.hidden) {
      closeBooking();
    }
  });

  // PROPERTY CHANGE
  $id("propertyType").addEventListener(
    "change", () => {
      updateCondo();
      updateReceipt();
    }
  );

  // LIVE RECEIPT CALCULATION
  form.addEventListener("input", updateReceipt);
  form.addEventListener("change", updateReceipt);

  // SAVE BOOKING
  // Capture the submission before any older delegated
  // booking handlers in main.js can submit it again.
  const basket=[];
  let checkoutKey=crypto.randomUUID();
  const box=document.createElement('section');
  box.className='checkout-box';
  box.innerHTML=`<h3>Your service cart</h3><p>Add the current service and schedule, then choose another service. Each item gets its own booking reference.</p>
  <div class="checkout-actions"><button type="button" class="btn" id="addCart">Add current service</button>
  <label>Next service <select id="nextService"></select></label></div>
  <ul id="cartItems" aria-live="polite"></ul><p id="cartTotal"></p>
  <label>Down-payment method <select name="payment_method" id="paymentMethod"><option value="" selected disabled>Select payment method</option><option value="gcash">GCash / Maya</option><option value="bank">Bank transfer (BPI / PNB)</option><option value="cash">Cash arrangement</option></select></label>
  <p id="paymentHelp" role="status">Select a payment method to see the account details and QR code.</p>
  <div id="payDetails" class="pay-details" hidden></div>
  <label id="paymentReferenceWrap" hidden>Transfer reference number (required)<input name="payment_reference" maxlength="100" autocomplete="off" placeholder="Enter the reference number from your payment"><small id="refHint" class="small-hint"></small></label>
  <label id="proofWrap" hidden>Screenshot of your payment (required)<input type="file" id="proofFile" accept="image/jpeg,image/png,image/webp"><small id="proofHint" class="small-hint">Attach a screenshot or photo of your receipt. It should show the amount, date and reference number. JPG, PNG or WebP.</small><img id="proofPreview" class="proof-preview" alt="Preview of your payment screenshot" hidden></label>
  <p id="depositInfo">Loading down-payment policy…</p>
  <p>References are reviewed by staff. Adding a reference does not confirm payment. Estimates and schedules require confirmation.</p>`;
  form.querySelector('.booking-submit').before(box);
  const next=box.querySelector('#nextService');
  next.innerHTML=SERVICES.map(s=>`<option value="${s.id}">${s.name}</option>`).join('');
  let policy=null;
  const policyReady=fetch('backend/payment-options.php').then(readResponse).then(d=>{policy=d;paymentHelp();drawBasket()}).catch(e=>{box.querySelector('#depositInfo').textContent=e.message;renderPayDetails(null,box.querySelector('#paymentMethod').value);});
  // Reference-number rules per payment method come from backend/payment-options.php.
  function refRule(){return policy?.reference?.[box.querySelector('#paymentMethod').value]||null;}
  function refError(v){
    const r=refRule();
    if(!v)return 'Please enter the reference number from your payment receipt.';
    if(!r)return '';
    if(r.digits_only&&!/^[0-9]+$/.test(v))return 'The reference number must contain digits only.';
    if(!r.digits_only&&!/^[A-Za-z0-9]+$/.test(v))return 'The reference number may only contain letters and numbers (no spaces or symbols).';
    if(v.length<r.min||v.length>r.max)return r.min===r.max?`The reference number must be exactly ${r.min} characters.`:`The reference number must be ${r.min}-${r.max} characters long.`;
    return '';
  }
  const payChannel={};   // remembers which tab (e.g. GCash / Maya, BPI / PNB) the customer picked
  const reduceMotion=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
  let swapping=false;
  // how: 'method' = customer just picked a payment method, 'channel' = customer switched tab (GCash/Maya, BPI/PNB)
  function renderPayDetails(acct,method,how,dir){
    const host=box.querySelector('#payDetails');host.replaceChildren();host.classList.remove('pay-enter');
    if(!method||method==='cash'){host.hidden=true;return;}
    host.hidden=false;
    if(how==='method'&&!reduceMotion){void host.offsetWidth;host.classList.add('pay-enter');}
    if(!acct){
      const m=document.createElement('p');
      m.textContent='Payment account details are unavailable right now. Please contact CLEANTEE at +63 998 577 5917 before sending payment.';
      host.append(m);return;
    }
    const chans=acct.channels||[acct];
    const ch=chans.find(c=>c.key===payChannel[method])||chans[0];
    const h=document.createElement('h4');h.textContent=acct.title;host.append(h);
    if(chans.length>1){
      const tabs=document.createElement('div');tabs.className='pay-tabs';tabs.setAttribute('role','tablist');
      for(const c of chans){
        const t=document.createElement('button');
        t.type='button';t.className='pay-tab'+(c===ch?' on':'')+(c===ch&&how==='channel'?' pop':'');t.textContent=c.label;t.setAttribute('role','tab');t.setAttribute('aria-selected',c===ch?'true':'false');
        t.onclick=()=>{
          if(c===ch||swapping)return;
          const d=chans.indexOf(c)>chans.indexOf(ch)?'r':'l';
          payChannel[method]=c.key;
          [...tabs.children].forEach(x=>{x.classList.toggle('on',x===t);x.setAttribute('aria-selected',x===t?'true':'false');});
          const cur=host.querySelector('.pay-body');
          if(cur&&!reduceMotion){swapping=true;cur.classList.add('pay-out-'+d);setTimeout(()=>{swapping=false;renderPayDetails(acct,method,'channel',d);},170);}
          else renderPayDetails(acct,method,'channel',d);
        };
        tabs.append(t);
      }
      host.append(tabs);
    }
    const body=document.createElement('div');
    body.className='pay-body'+(how==='channel'&&!reduceMotion?' pay-in-'+(dir||'r'):'');
    host.append(body);
    renderChannel(body,ch);
  }
  function renderChannel(host,a){
    if(a.holder){const p=document.createElement('p');p.className='pay-holder';p.textContent='Account name: '+a.holder;host.append(p);}
    if(!(a.rows||[]).length&&!a.qr){const p=document.createElement('p');p.textContent='Details for this option are coming soon. Please contact CLEANTEE at +63 998 577 5917.';host.append(p);}
    for(const r of a.rows||[]){
      const row=document.createElement('div'),l=document.createElement('span'),v=document.createElement('b'),c=document.createElement('button');
      row.className='pay-row';l.textContent=r.label;v.textContent=r.value;
      c.type='button';c.className='pay-copy';c.textContent='Copy';
      c.onclick=async()=>{try{await navigator.clipboard.writeText(r.copy||r.value);c.textContent='Copied ✓';}catch(e){c.textContent='Select and copy';}setTimeout(()=>{c.textContent='Copy'},2000)};
      row.append(l,v,c);host.append(row);
    }
    if(a.qr){
      const f=document.createElement('figure'),img=document.createElement('img'),cap=document.createElement('figcaption');
      f.className='pay-qr';img.src=a.qr.src;img.alt=a.qr.caption||'Payment QR code';img.loading='lazy';cap.textContent=a.qr.caption||'';
      f.append(img,cap);host.append(f);
    }
    if(a.note){const n=document.createElement('p');n.className='small-hint';n.textContent=a.note;host.append(n);}
  }
  /* 24-hour notice rule (the server checks it again) */
  function readWhen(dv,tv){
    // Accepts 2026-11-04, 11/04/2026, or "Nov 4, 2026" and 10:00, 10:00:00 or 10:00 AM. Returns a time in ms (Manila time) or null.
    let y,mo,da,h,mi,m;
    dv=String(dv||"").trim();tv=String(tv||"").trim();
    if((m=/(\d{4})-(\d{1,2})-(\d{1,2})/.exec(dv))){y=+m[1];mo=+m[2];da=+m[3];}
    else if((m=/(\d{1,2})\/(\d{1,2})\/(\d{4})/.exec(dv))){mo=+m[1];da=+m[2];y=+m[3];}
    else{const t=Date.parse(dv+" 12:00");if(isNaN(t))return null;const x=new Date(t);y=x.getFullYear();mo=x.getMonth()+1;da=x.getDate();}
    if(!(m=/(\d{1,2}):(\d{2})\s*([AaPp][Mm])?/.exec(tv)))return null;
    h=+m[1];mi=+m[2];
    if(m[3]){const pm=/p/i.test(m[3]);if(pm&&h<12)h+=12;if(!pm&&h===12)h=0;}
    return Date.UTC(y,mo-1,da,h-8,mi);
  }
  function checkNotice(){
    const d=$id("bookingDate"),t=$id("bookingTime");if(!d||!t)return true;
    let hint=document.getElementById("noticeHint");
    if(!hint){hint=document.createElement("small");hint.id="noticeHint";hint.className="small-hint";t.parentElement.append(hint);}
    const base="Please book at least 24 hours ahead.";
    t.setCustomValidity("");
    if(!d.value||!t.value){hint.textContent=base;hint.classList.remove("bad");return true;}
    const when=readWhen(d.value,t.value);
    if(when===null){
      // Could not read the value: do not block the customer, the server checks the rule again.
      hint.textContent=base;hint.classList.remove("bad");return true;
    }
    if(when<Date.now()+24*3600*1000){
      t.setCustomValidity("Please choose a time at least 24 hours from now.");
      hint.textContent=base+" (You chose "+d.value+" "+t.value+".)";hint.classList.add("bad");return false;
    }
    hint.textContent=base;hint.classList.remove("bad");return true;
  }
  /* Payment screenshot: required for GCash / Maya and bank transfers. Large photos are shrunk before upload. */
  let proofBlob=null;
  function clearProof(){
    proofBlob=null;const inp=box.querySelector('#proofFile'),pv=box.querySelector('#proofPreview'),h=box.querySelector('#proofHint');
    if(inp)inp.value='';if(pv){if(pv.src.startsWith('blob:'))URL.revokeObjectURL(pv.src);pv.removeAttribute('src');pv.hidden=true;}
    if(h){h.classList.remove('bad','ok');}
  }
  async function shrinkImage(file){
    if(file.size<=1024*1024)return file;
    try{
      const bmp=await createImageBitmap(file),max=1600,k=Math.min(1,max/Math.max(bmp.width,bmp.height));
      const c=document.createElement('canvas');c.width=Math.round(bmp.width*k);c.height=Math.round(bmp.height*k);
      const ctx=c.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,c.width,c.height);ctx.drawImage(bmp,0,0,c.width,c.height);
      const out=await new Promise(r=>c.toBlob(r,'image/jpeg',0.85));
      return out&&out.size<file.size?new File([out],'payment.jpg',{type:'image/jpeg'}):file;
    }catch(e){return file;}
  }
  function paymentHelp(how){
    const method=box.querySelector('#paymentMethod').value,none=!method,cash=method==='cash',ref=form.elements.payment_reference;
    if(!policy){renderPayDetails(null,method);return;}
    const rule=refRule();
    box.querySelector('#paymentHelp').textContent=none?'Select a payment method to see the account details and QR code.':(policy.instructions[method]||'');
    renderPayDetails(policy.accounts?.[method]||null,method,how);
    const refWrap=box.querySelector('#paymentReferenceWrap');
    refWrap.hidden=none||cash;
    const proofWrap=box.querySelector('#proofWrap');proofWrap.hidden=none||cash;
    if(none||cash)clearProof();
    if(how==='method'&&!refWrap.hidden&&!reduceMotion){refWrap.classList.remove('soft-reveal');void refWrap.offsetWidth;refWrap.classList.add('soft-reveal');}
    ref.disabled=none||cash;
    if(none||cash){ref.value='';return;}
    if(rule){ref.minLength=rule.min;ref.maxLength=rule.max;ref.inputMode=rule.digits_only?'numeric':'text';ref.placeholder=rule.placeholder||'';box.querySelector('#refHint').textContent=rule.hint||'';}
    ref.value=ref.value.replace(rule?.digits_only?/[^0-9]/g:/[^A-Za-z0-9]/g,'').slice(0,rule?.max||100);
  }
  box.querySelector('#paymentMethod').onchange=()=>paymentHelp('method');
  box.querySelector('#proofFile').addEventListener('change',async e=>{
    const inp=e.target,h=box.querySelector('#proofHint'),pv=box.querySelector('#proofPreview'),f=inp.files&&inp.files[0];
    inp.setCustomValidity('');proofBlob=null;pv.hidden=true;
    if(!f)return;
    if(!/^image\/(jpeg|png|webp)$/.test(f.type)){clearProof();h.textContent='Only JPG, PNG or WebP images are accepted.';h.classList.add('bad');return;}
    const out=await shrinkImage(f);
    if(out.size>4*1024*1024){clearProof();h.textContent='That image is too large (over 4 MB). Please choose a smaller screenshot.';h.classList.add('bad');return;}
    proofBlob=out;
    if(pv.src.startsWith('blob:'))URL.revokeObjectURL(pv.src);
    pv.src=URL.createObjectURL(out);pv.hidden=false;
    h.textContent='Attached: '+f.name;h.classList.remove('bad');h.classList.add('ok');
  });
  // Keep only allowed characters while typing or pasting (spaces and dashes are removed).
  form.elements.payment_reference.addEventListener('input',e=>{
    const r=refRule(),el=e.target;
    el.value=el.value.replace(r?.digits_only?/[^0-9]/g:/[^A-Za-z0-9]/g,'').slice(0,r?.max||100);
  });
  /* The date and time pickers on this page can set their value without a normal input event, so re-check on every kind
     of event and, while the booking window is open, every half second. */
  ['bookingDate','bookingTime'].forEach(i=>{const el=$id(i);if(el)['input','change','blur','keyup','click'].forEach(ev=>el.addEventListener(ev,()=>setTimeout(checkNotice,0)));});
  setInterval(()=>{if(!modal.hidden)checkNotice();},500);
  form.addEventListener('input',()=>{checkoutKey=crypto.randomUUID()});
  form.addEventListener('change',()=>{checkoutKey=crypto.randomUUID()});
  next.onchange=()=>{current=next.value;serviceInput.value=current;updateFields();updateReceipt()};
  function snapshot(){
    const estimate=updateReceipt(),fd=new FormData(form);fd.set('service',current);fd.set('cleaners',String(estimate.cleaners));
    return {data:Object.fromEntries(fd),estimate:estimate.totalMin};
  }
  function drawBasket(){
    const list=box.querySelector('#cartItems');list.replaceChildren();
    basket.forEach((item,i)=>{const li=document.createElement('li'),remove=document.createElement('button');remove.type='button';remove.className='cart-remove';remove.textContent='Remove';remove.onclick=()=>{if(submitButton.disabled)return;basket.splice(i,1);checkoutKey=crypto.randomUUID();drawBasket()};li.append(`${SERVICES.find(s=>s.id===item.data.service)?.name}: ${item.data.date} ${item.data.time} — ${money(item.estimate)} `,remove);list.append(li)});
    const total=basket.reduce((n,i)=>n+i.estimate,0);
    box.querySelector('#cartTotal').textContent=basket.length?`${basket.length} service(s) · Estimated total from ${money(total)}`:'Your cart is empty. You can also submit just the current service.';
    if(policy)box.querySelector('#depositInfo').textContent=`Down payment: ${policy.percent}% of the server-confirmed estimate`+(basket.length?` · approximately ${money(total*policy.percent/100)}`:'');
    submitButton.textContent=basket.length?'Submit cart ('+basket.length+')':'Confirm Booking';
  }
  box.querySelector('#addCart').onclick=async()=>{
    if(submitButton.disabled)return;
    await authReady;if(!SESSION()){location.href='auth.html';return;}
    checkNotice();
    if(!form.reportValidity())return;if(basket.length>=20){alert('Maximum 20 services per checkout.');return;}
    basket.push(snapshot());checkoutKey=crypto.randomUUID();drawBasket();
  };
  form.addEventListener("submit", async event => {
    event.preventDefault();event.stopImmediatePropagation();
    await authReady;await policyReady;
    if(!SESSION()){sessionStorage.setItem('ct_booking_return','services.html?book=1');location.href='auth.html';return;}
    if(submitButton.disabled)return;
    const methodSel=form.elements.payment_method;
    if(!methodSel.value){
      methodSel.setCustomValidity('Please select a payment method.');
      methodSel.reportValidity();
      methodSel.addEventListener('change',()=>methodSel.setCustomValidity(''),{once:true});
      return;
    }
    const refBox=form.elements.payment_reference;
    if(!refBox.disabled){
      const msg=refError(refBox.value.trim());
      if(msg){
        refBox.setCustomValidity(msg);
        refBox.reportValidity();
        refBox.addEventListener('input',()=>refBox.setCustomValidity(''),{once:true});
        return;
      }
    }
    if(methodSel.value!=='cash'&&!proofBlob){
      const pf=box.querySelector('#proofFile');
      pf.setCustomValidity('Please attach a screenshot of your payment.');
      pf.reportValidity();
      pf.addEventListener('change',()=>pf.setCustomValidity(''),{once:true});
      return;
    }
    checkNotice();
    if(!basket.length&&!checkNotice()){$id('bookingTime').reportValidity();return;}
    const items=basket.length?basket.map(i=>i.data):form.reportValidity()?[snapshot().data]:null;
    if(!items)return;
    submitButton.disabled=true;submitButton.textContent='Submitting…';
    try{
      const fd=new FormData();fd.set('items',JSON.stringify(items));fd.set('checkout_key',checkoutKey);fd.set('payment_method',form.elements.payment_method.value);fd.set('payment_reference',form.elements.payment_reference.value);
      if(proofBlob&&form.elements.payment_method.value!=='cash')fd.set('proof',proofBlob,proofBlob.name||'payment.jpg');
      const result=await api('booking',fd);basket.length=0;checkoutKey=crypto.randomUUID();drawBasket();closeBooking();
      // Show the confirmation page (with a printable receipt) right after a successful booking.
      location.href='receipt.html?new=1&ref='+encodeURIComponent(result.ref);
      return;
    }catch(error){
      if(error.code==='proof'){
        const pf=box.querySelector('#proofFile');clearProof();
        pf.setCustomValidity(error.message);pf.reportValidity();
        pf.addEventListener('change',()=>pf.setCustomValidity(''),{once:true});
      }else if(error.code==='duplicate_reference'){
        // The reference number was already used: point at the field instead of a generic popup.
        const rb=form.elements.payment_reference;
        rb.setCustomValidity(error.message);rb.reportValidity();
        rb.addEventListener('input',()=>rb.setCustomValidity(''),{once:true});
      }else if(error.code==='csrf'){
        try{await refreshSession();}catch(e){}
        alert('Your page was idle for a while, so we refreshed your session. Please press Confirm Booking again.');
      }else if(error.status===401){
        alert('Your login session has expired. Please log in again. Your booking was not saved.');
        sessionStorage.setItem('ct_booking_return','services.html?book=1');location.href='auth.html';
      }else if(error.status===429){
        alert(error.message||'Too many attempts. Please wait a few minutes and try again.');
      }else if(error instanceof TypeError){
        alert('We could not reach the server. Please check your internet connection and try again. Nothing was charged and your details are still on this page.');
      }else{
        alert(error.message||'Could not save your booking. Please try again, or call CLEANTEE at +63 998 577 5917.');
      }
    }
    finally{submitButton.disabled=false;drawBasket();}
  },true);
  drawBasket();


  // MAP HELP: link under the address that opens the typed address in Google Maps
  (function(){
    const addr=$id("bookingAddress"),city=$id("bookingCity");
    if(!addr||!addr.parentElement||addr.parentElement.querySelector(".map-link"))return;
    const a=document.createElement("a");
    a.className="map-link";a.target="_blank";a.rel="noopener";a.textContent="📍 Check this address on Google Maps";a.hidden=true;
    addr.parentElement.append(a);
    const upd=()=>{
      const q=[addr.value.trim(),city&&city.value&&city.value!=="Other"?city.value:"","Philippines"].filter(Boolean).join(", ");
      a.hidden=addr.value.trim().length<5;
      a.href="https://www.google.com/maps/search/?api=1&query="+encodeURIComponent(q);
    };
    addr.addEventListener("input",upd);
    if(city)city.addEventListener("change",upd);
    upd();
  })();

  // MAP LOCATION: optional Google Maps link, pasted by the customer or filled with "Use my location"
  (function(){
    const input=$id('bookingMap'),btn=$id('useLocation'),hint=$id('mapHint');
    if(!input||!btn||!hint)return;
    const DEFAULT=hint.textContent;
    const okHost=h=>/^(www\.)?google\.(com|com\.ph)$|^maps\.google\.(com|com\.ph)$|^maps\.app\.goo\.gl$|^goo\.gl$/.test(h);
    function say(text,cls){hint.className='small-hint'+(cls?' '+cls:'');hint.textContent=text;}
    function check(){
      const v=input.value.trim();input.setCustomValidity('');
      if(!v){say(DEFAULT);return true;}
      let u=null;try{u=/\s/.test(v)?null:new URL(v);}catch(e){u=null;}
      const good=u&&u.protocol==='https:'&&okHost(u.hostname.toLowerCase())&&(!/^(www\.)?google\./.test(u.hostname.toLowerCase())||u.pathname.indexOf('/maps')===0);
      if(!good){
        const m='This is not a Google Maps link. Paste one that starts with https://maps.app.goo.gl or https://www.google.com/maps, or leave it empty.';
        input.setCustomValidity(m);say(m,'bad');return false;
      }
      hint.className='small-hint ok';hint.textContent='Map location added. ';
      const a=document.createElement('a');a.href=u.href;a.target='_blank';a.rel='noopener';a.textContent='Preview on map';hint.append(a);
      return true;
    }
    input.addEventListener('input',check);
    btn.addEventListener('click',()=>{
      if(!navigator.geolocation){say('This browser cannot share your location. Please paste a Google Maps link instead.','bad');return;}
      const label=btn.textContent;btn.disabled=true;btn.textContent='Finding you…';
      navigator.geolocation.getCurrentPosition(pos=>{
        const lat=pos.coords.latitude.toFixed(6),lng=pos.coords.longitude.toFixed(6);
        input.value='https://www.google.com/maps?q='+lat+','+lng;
        input.dispatchEvent(new Event('input',{bubbles:true}));
        const acc=Math.round(pos.coords.accuracy||0);
        const note=document.createElement('span');note.textContent=' '+(acc?'Accurate to about '+acc+' m. ':'')+'Please make sure the pin is at the service address.';
        hint.append(note);
        btn.disabled=false;btn.textContent=label;
      },err=>{
        const msg=err&&err.code===1?'Location access was blocked. Allow it in your browser settings, or paste a Google Maps link instead.'
          :err&&err.code===3?'Finding your location took too long. Try again, or paste a Google Maps link instead.'
          :'We could not find your location. Try again, or paste a Google Maps link instead.';
        say(msg,'bad');btn.disabled=false;btn.textContent=label;
      },{enableHighAccuracy:true,timeout:12000,maximumAge:60000});
    });
  })();

  // AUTO-OPEN
  // - "Book this" on a service (?service=...) opens that service's form.
  // - A plain "Book now" (home page) just shows the list of services to choose from.
  // - Returning from the login page re-opens the form the customer was trying to open.
  const params = new URLSearchParams(location.search);
  let resume = false;
  try { resume = sessionStorage.getItem("ct_booking_resume") === "1"; sessionStorage.removeItem("ct_booking_resume"); } catch (e) {}

  if (params.has("service") || (params.has("book") && resume)) {
    openBooking(params.get("service") || "");
  } else if (params.has("book")) {
    try { history.replaceState(null, "", location.pathname); } catch (e) {}
  }
})();
