/**
 * Prakruthi Siri - Customer Storefront, Receipt & Tracker Multilingual Dictionary
 * Pure Single-Language Architecture: English or Telugu, never mixed.
 * All keys must exist in both 'te' and 'en'. No bilingual slash/bullet values.
 */

const CUSTOMER_I18N = {
  te: {
    // -------------------------------------------------------------------------
    // Brand
    // -------------------------------------------------------------------------
    brand_title: "ప్రకృతి సిరి",
    brand_subtitle: "రసాయనాలు లేని తాజా కూరగాయలు",

    // -------------------------------------------------------------------------
    // Onboarding / Mobile Check-In
    // -------------------------------------------------------------------------
    onboarding_badge: "మొబైల్ చెక్-ఇన్",
    onboarding_hint: "మీ 10-అంకెల మొబైల్ నంబర్ నమోదు చేయండి, కూరగాయల జాబితా తెరుచుకుంటుంది.",
    btn_continue: "కొనసాగించండి →",

    // -------------------------------------------------------------------------
    // Returning Customer
    // -------------------------------------------------------------------------
    returning_badge: "మీ ప్రొఫైల్",
    returning_welcome: "తిరిగి స్వాగతం,",
    btn_change_mobile: "మొబైల్ మార్చు",
    lbl_select_address: "డెలివరీ చిరునామా ఎంచుకోండి",
    btn_confirm_address: "✓ చిరునామా నిర్ధారించి కూరగాయలు చూడండి →",
    btn_add_new_address: "+ కొత్త చిరునామా జోడించు",
    gps_pinned_badge: "📍 GPS నమోదైంది",

    // -------------------------------------------------------------------------
    // Inline New Address Form
    // -------------------------------------------------------------------------
    inline_addr_title: "కొత్త డెలివరీ చిరునామా జోడించు",
    lbl_addr_label: "లేబల్ (ఉదా: ఇల్లు, కార్యాలయం)",
    placeholder_addr_label: "ఇల్లు",
    lbl_addr_street: "వీధి చిరునామా *",
    placeholder_addr_street: "ఫ్లాట్/ఇంటి నెం, కాలనీ లేదా వీధి",
    lbl_addr_landmark: "గుర్తు (ఐచ్ఛికం)",
    placeholder_addr_landmark: "ఉదా: గణేష్ ఆలయం దగ్గర",
    gps_section_title: "📍 GPS డోర్‌స్టెప్ లొకేషన్",
    btn_use_gps: "📍 GPS వాడండి",
    btn_pin_map: "🗺️ మ్యాప్‌లో పిన్ చేయండి",
    placeholder_maps_link: "WhatsApp / Google Maps లింక్ పేస్ట్ చేయండి",
    map_hint: "GPS వాడండి లేదా మ్యాప్‌లో మీ ఇంటిని పిన్ చేయండి.",
    map_drag_hint: "మీ ఇంటి లొకేషన్ సెట్ చేయడానికి పిన్ లాగండి",
    lbl_addr_city: "నగరం / ప్రాంతం *",
    btn_save_address: "చిరునామా సేవ్ చేయండి",

    // -------------------------------------------------------------------------
    // New Customer Form
    // -------------------------------------------------------------------------
    new_cust_header: "కొత్త కస్టమర్ వివరాలు",
    new_cust_badge: "మొదటి నమోదు",
    lbl_new_name: "పూర్తి పేరు *",
    placeholder_new_name: "ఉదా: రమేష్ శర్మ",
    lbl_new_address: "డెలివరీ చిరునామా *",
    placeholder_new_address: "ఫ్లాట్/ఇంటి నెం, అపార్ట్‌మెంట్ లేదా వీధి",
    lbl_new_landmark: "గుర్తు (ఐచ్ఛికం)",
    placeholder_new_landmark: "ఉదా: గణేష్ ఆలయం ఎదురుగా",
    gps_section_desc: "ఉదయం డెలివరీ వేగంగా చేయడానికి మీ ఇంటి లొకేషన్ పిన్ చేయండి",
    lbl_new_locality: "నగరం ఎంచుకోండి *",
    kazipet_note: "కాజీపేట ప్రస్తుతం డెలివరీ జోన్ వెలుపల ఉంది.",
    btn_save_proceed: "సేవ్ చేసి కూరగాయలు చూడండి →",

    // -------------------------------------------------------------------------
    // Confirmed Address Strip
    // -------------------------------------------------------------------------
    lbl_delivering_to: "డెలివరీ గమ్యం",
    btn_change_address: "మార్చండి",

    // -------------------------------------------------------------------------
    // Locked Catalog Card
    // -------------------------------------------------------------------------
    locked_title: "తాజా సేంద్రీయ కూరగాయలు",
    locked_desc: "మీ 10-అంకెల మొబైల్ నంబర్ నమోదు చేస్తే ఈ రోజు తాజా కూరగాయల జాబితా తెరుచుకుంటుంది.",
    locked_badge: "⚡ మొబైల్ చెక్-ఇన్ తో కూరగాయలు చూడండి",

    // -------------------------------------------------------------------------
    // Batch / Delivery Selector
    // -------------------------------------------------------------------------
    badge_delivery_batch: "డెలివరీ బ్యాచ్",
    title_delivery_batch: "డెలివరీ ప్రాంతం ఎంచుకోండి",
    title_schedule_locked: "మీ డెలివరీ షెడ్యూల్",
    lbl_delivering_to_locked: "మీ చిరునామా ఆధారంగా డెలివరీ ప్రాంతం:",
    lbl_auto_locked_badge: "✓ ఆటో-లాక్ అయింది",
    bookings_open: "బుకింగ్స్ ఓపెన్",
    bookings_closed: "బుకింగ్ ముగిసింది",
    bookings_upcoming: "బుకింగ్ త్వరలో ప్రారంభం",
    lbl_next_delivery: "డెలివరీ తేదీ:",
    lbl_cutoff_time: "బుకింగ్ ముగిసే సమయం:",
    batch_capacity_label: "బ్యాచ్ కెపాసిటీ:",
    batch_slots_booked: function(n) { return "బుకింగ్స్ నిండాయి"; },
    batch_full_msg: "బ్యాచ్ నిండిపోయింది. కార్ట్ నిలిపివేయబడింది.",
    hanamkonda_label: "హనుమకొండ",
    warangal_label: "వరంగల్",

    // -------------------------------------------------------------------------
    // Step 2: Vegetable Catalog
    // -------------------------------------------------------------------------
    badge_step2: "స్టెప్ 2",
    title_step2: "తోట నుండి తాజా కూరగాయలు",
    unit_per_half_kg: "/ 0.5 కేజీ",
    sold_out: "అయిపోయింది",
    available_stock: "అందుబాటులో ఉంది",
    varieties_label: function(n) { return n + " రకాలు"; },
    catalog_empty: "ఈ బ్యాచ్కి కూరగాయలు అందుబాటులో లేవు.",

    // -------------------------------------------------------------------------
    // Sticky Cart & Drawer
    // -------------------------------------------------------------------------
    basket_label: "కూరగాయల బుట్ట",
    cart_count: function(n, kg) { return "(" + n + " ప్యాకెట్లు • " + kg + " కేజీ)"; },
    btn_review_order: "ఆర్డర్ చేయండి →",
    drawer_title: "ఆర్డర్ మరియు చెక్అవుట్",
    drawer_basket_heading: "బుట్టలోని కూరగాయలు",
    cart_empty: "బుట్ట ఖాళీగా ఉంది",
    cart_item_qty: function(qty, kg) { return qty + " ప్యాకెట్లు (" + kg + " కేజీ)"; },

    // Drawer address card
    lbl_drawer_address: "డెలివరీ చిరునామా",
    btn_drawer_change: "మార్చు",

    // Checkout form labels
    name_label: "మీ పూర్తి పేరు *",
    name_placeholder: "ఉదా: రమేష్ శర్మ",
    phone_label: "10-అంకెల మొబైల్ నంబర్ *",
    phone_placeholder: "9876543210",
    address_label: "ఇంటి నెం, అపార్ట్‌మెంట్ / వీధి చిరునామా *",
    address_placeholder: "ఫ్లాట్ 302, శ్రీ సాయి రెసిడెన్సీ, మెయిన్ రోడ్",
    landmark_label: "గుర్తు (ఐచ్ఛికం)",
    landmark_placeholder: "ఉదా: గణేష్ ఆలయం ఎదురుగా",
    locality_label: "నగరం / ప్రాంతం *",

    // Payment
    payment_mode_label: "చెల్లింపు విధానం *",
    pay_cod_label: "నగదు (COD)",
    pay_cod_sub: "ఇంటి వద్ద చెల్లించండి",
    pay_upi_label: "ఆన్‌లైన్ UPI",
    pay_upi_sub: "GPay / PhonePe / Paytm",

    // Bill breakdown
    items_subtotal: "కూరగాయల మొత్తం:",
    delivery_fee: "డెలివరీ ఛార్జీ:",
    delivery_free: "ఉచితం",
    mov_alert: function(shortfall) { return "ఇంకా ₹" + shortfall + " ఆర్డర్ చేస్తే ఉచిత డెలివరీ లభిస్తుంది!"; },
    total_payable: "మొత్తం చెల్లించాల్సింది:",

    // Submit
    btn_confirm_order: "ఆర్డర్ నిర్ధారించండి →",
    placing_order: "ఆర్డర్ నమోదు చేస్తోంది...",
    batch_full_btn: "ఈ బ్యాచ్ నిండిపోయింది",

    // -------------------------------------------------------------------------
    // Alerts & Validations
    // -------------------------------------------------------------------------
    empty_basket_alert: "మీ బుట్ట ఖాళీగా ఉంది. దయచేసి కూరగాయలను ఎంచుకోండి.",
    phone_invalid_alert: "దయచేసి సరైన 10 అంకెల మొబైల్ నంబర్ నమోదు చేయండి.",
    name_required_alert: "దయచేసి మీ పూర్తి పేరు నమోదు చేయండి.",
    address_required_alert: "దయచేసి మీ చిరునామా నమోదు చేయండి.",
    select_address_alert: "దయచేసి ఒక చిరునామా ఎంచుకోండి.",
    street_required_alert: "దయచేసి వీధి చిరునామా నమోదు చేయండి.",
    stock_limit_alert: function(name, stock) { return name + ": అందుబాటులో " + stock + " ప్యాకెట్లు మాత్రమే ఉన్నాయి."; },
    batch_full_alert: "క్షమించండి! ఈ డెలివరీ బ్యాచ్ నిండిపోయింది.",
    gps_locating: "GPS పరీక్షిస్తోంది...",
    gps_latched: "✓ GPS నమోదైంది",
    gps_unsupported: "మీ బ్రౌజర్‌లో GPS పనిచేయదు.",
    gps_failed: "GPS లొకేషన్ పొందలేకపోయింది. Maps లింక్ పేస్ట్ చేయండి లేదా మ్యాప్‌లో పిన్ చేయండి.",
    saving_order: "ఆర్డర్ భద్రపరుస్తోంది...",

    // -------------------------------------------------------------------------
    // Order Receipt (order-success.php)
    // -------------------------------------------------------------------------
    receipt_title: "ఆర్డర్ విజయవంతంగా నమోదైంది!",
    receipt_subtitle: "ధన్యవాదాలు! తాజా కూరగాయలు మీ ఇంటికి సమయానికి చేరుతాయి.",
    receipt_delivery_label: "షెడ్యూల్ చేసిన డెలివరీ:",
    receipt_order_ref: "ఆర్డర్ కోడ్",
    receipt_payment_mode: "చెల్లింపు పద్ధతి",
    receipt_delivering_to: "డెలివరీ చిరునామా",
    receipt_basket_heading: "మీ బుట్టలోని కూరగాయలు",
    receipt_packet_unit: function(pkts, kg) { return pkts + " ప్యాకెట్లు (" + kg + " కిలోలు)"; },
    receipt_subtotal: "కూరగాయల మొత్తం:",
    receipt_delivery_fee: "డెలివరీ ఛార్జ్:",
    receipt_fee_free: "ఉచితం",
    receipt_grand_total: "మొత్తం చెల్లించాల్సింది:",
    receipt_paid_cod: "క్యాష్ ఆన్ డెలివరీ (COD)",
    receipt_paid_upi: "ఆన్‌లైన్ (UPI)",
    btn_send_whatsapp: "💬 వాట్సాప్‌లో వివరాలు పంపండి",
    btn_track_order: "📍 ఆర్డర్ ట్రాక్ చేయండి →",
    btn_shop_more: "← మరిన్ని కూరగాయలు చూడండి",
    receipt_home_link: "← హోమ్",

    // -------------------------------------------------------------------------
    // Live Order Tracker (track.php)
    // -------------------------------------------------------------------------
    tracker_tagline: "ఆర్డర్ లైవ్ ట్రాకర్",
    tracker_store_link: "స్టోర్ →",
    tracker_progress_title: "ఆర్డర్ పురోగతి",
    tracker_step1_title: "ఆర్డర్ నమోదైంది",
    tracker_step1_desc: "ఆర్డర్ నిర్ధారించబడి పంపిణీ క్యూలో చేర్చబడింది.",
    tracker_step2_title: "కూరగాయలు ప్యాక్ అయ్యాయి",
    tracker_step2_desc: "తాజా కూరగాయలు కోసి ప్యాక్ చేయబడ్డాయి.",
    tracker_step3_title: "డెలివరీ బయలుదేరింది",
    tracker_step3_desc: "డ్రైవర్ మీ ఇంటికి బయలుదేరాడు.",
    tracker_step4_title: "ఇంటి వద్ద చేరింది",
    tracker_step4_desc: "డోర్‌స్టెప్ డెలివరీ పూర్తయింది.",
    tracker_cancelled_msg: "ఈ ఆర్డర్ రద్దు చేయబడింది మరియు స్టాక్ తిరిగి జోడించబడింది.",
    tracker_items_heading: "ఆర్డర్ చేసిన కూరగాయలు",
    tracker_subtotal: "మొత్తం:",
    tracker_delivery_fee: "డెలివరీ ఛార్జ్:",
    tracker_grand_total: "మొత్తం:",
    tracker_payment_label: "చెల్లింపు:",
    tracker_delivery_batch: "డెలివరీ బ్యాచ్:",
    tracker_recipient: "స్వీకర్త:",
    tracker_address: "చిరునామా:",
    tracker_placed_at: "ఆర్డర్ సమయం:",
    btn_order_more: "+ కొత్త ఆర్డర్ చేయండి",
    btn_whatsapp_support: "💬 WhatsApp సహాయం",
    tracker_search_placeholder: "ఆర్డర్ కోడ్ నమోదు చేయండి (ఉదా: PS-1031)...",
    tracker_phone_placeholder: "10-అంకెల మొబైల్ నంబర్...",
    tracker_track_btn: "ట్రాక్ చేయండి",
    tracker_empty_state_title: "మీ ఆర్డర్ ట్రాక్ చేయండి",
    tracker_empty_state_desc: "మీ ఆర్డర్ కోడ్ నమోదు చేయండి, రియల్-టైమ్ డెలివరీ స్థితి చూడండి.",
    tracker_item_qty: function(pkts, kg) { return pkts + " pkts (" + kg + " kg)"; },
    tracker_eta_heading: "అంచనా డెలివరీ సమయం",
    tracker_queue_heading: "మీ క్యూ స్థానం",
    tracker_queue_waiting: function(seq, total) { return "మీ ఆర్డర్ స్టాప్ " + seq + " / " + total; },
    tracker_queue_active: function(driverStop, mySeq) { return "డ్రైవర్ ప్రస్తుతం స్టాప్ " + driverStop + " వద్ద ఉన్నాడు; మీ డెలివరీ స్టాప్ " + mySeq; },
    tracker_eta_window: function(day, start, end) { return (day ? day + " " : "") + start + " – " + end + " మధ్యలో"; },

    // PWA
    pwa_banner_text: "సులభంగా ఆర్డర్ చేయడానికి Prakruthi Siri ని హోమ్ స్క్రీన్‌కి జోడించండి.",

    modal_region_title: "\u0c2e\u0c40 \u0c21\u0c46\u0c32\u0c3f\u0c35\u0c30\u0c40 \u0c2a\u0c4d\u0c30\u0c3e\u0c02\u0c24\u0c02 \u0c0e\u0c02\u0c1a\u0c41\u0c15\u0c4b\u0c02\u0c21\u0c3f",
    modal_region_desc: "\u0c2e\u0c47\u0c2e\u0c41 \u0c39\u0c28\u0c41\u0c2e\u0c15\u0c4a\u0c02\u0c21 \u0c2e\u0c30\u0c3f\u0c2f\u0c41 \u0c35\u0c30\u0c02\u0c17\u0c32\u0c4d\u0c32\u0c4b \u0c21\u0c46\u0c32\u0c3f\u0c35\u0c30\u0c40 \u0c1a\u0c47\u0c38\u0c4d\u0c24\u0c3e\u0c2e\u0c41.",
    modal_region_note: "\u0c15\u0c3e\u0c1c\u0c40\u0c2a\u0c47\u0c1f \u0c21\u0c46\u0c32\u0c3f\u0c35\u0c30\u0c40 \u0c1c\u0c4b\u0c28\u0c4d \u0c35\u0c46\u0c32\u0c41\u0c2a\u0c32 \u0c09\u0c02\u0c26\u0c3f.",
    locked_cross_title: "\u0c2e\u0c40 \u0c2a\u0c4d\u0c30\u0c3e\u0c02\u0c24\u0c3e\u0c28\u0c3f\u0c15\u0c3f \u0c06\u0c30\u0c4d\u0c21\u0c30\u0c4d\u0c32\u0c41 \u0c07\u0c02\u0c15\u0c3e \u0c24\u0c46\u0c30\u0c41\u0c1a\u0c41\u0c15\u0c4b\u0c32\u0c47\u0c26\u0c41",
    locked_cross_desc: function(a,b,c,d){return a+" \u0c06\u0c30\u0c4d\u0c21\u0c30\u0c4d\u0c32\u0c41 \u0c13\u0c2a\u0c46\u0c28\u0c4d \u0c09\u0c28\u0c4d\u0c28\u0c3e\u0c2f\u0c3f. "+c+" \u0c06\u0c30\u0c4d\u0c21\u0c30\u0c4d\u0c32\u0c41 "+d+" \u0c28 \u0c24\u0c46\u0c30\u0c41\u0c1a\u0c41\u0c15\u0c41\u0c02\u0c1f\u0c3e\u0c2f\u0c3f.";},
    locked_cross_opens_label: function(r,f){return r+" \u0c24\u0c46\u0c30\u0c41\u0c1a\u0c41\u0c15\u0c41\u0c02\u0c1f\u0c41\u0c02\u0c26\u0c3f: "+f;},
    locked_cross_wa_text: "\ud83d\udcac WhatsApp \u0c32\u0c4b \u0c17\u0c41\u0c30\u0c4d\u0c24\u0c41 \u0c1a\u0c47\u0c2f\u0c02\u0c21\u0c3f",
    locked_cross_change_btn: "\u0c2a\u0c4d\u0c30\u0c3e\u0c02\u0c24\u0c02 \u0c2e\u0c3e\u0c30\u0c4d\u0c1a\u0c02\u0c21\u0c3f",
    locked_closed_title: "\u0c08 \u0c2c\u0c4d\u0c2f\u0c3e\u0c1a\u0c4d \u0c06\u0c30\u0c4d\u0c21\u0c30\u0c4d\u0c32\u0c41 \u0c2e\u0c41\u0c17\u0c3f\u0c36\u0c3e\u0c2f\u0c3f",
    locked_closed_desc: function(r,f){return r+" \u0c24\u0c26\u0c41\u0c2a\u0c30\u0c3f \u0c2c\u0c4d\u0c2f\u0c3e\u0c1a\u0c4d "+f+" \u0c28 \u0c24\u0c46\u0c30\u0c41\u0c1a\u0c41\u0c15\u0c41\u0c02\u0c1f\u0c41\u0c02\u0c26\u0c3f.";},
    locked_closed_badge: function(f){return "\u0c24\u0c26\u0c41\u0c2a\u0c30\u0c3f \u0c2c\u0c4d\u0c2f\u0c3e\u0c1a\u0c4d: "+f;},
    locked_closed_change_btn: "\u0c2a\u0c4d\u0c30\u0c3e\u0c02\u0c24\u0c02 \u0c2e\u0c3e\u0c30\u0c4d\u0c1a\u0c02\u0c21\u0c3f",
    exp_title: "\u0c2e\u0c40 \u0c2a\u0c4d\u0c30\u0c3e\u0c02\u0c24\u0c3e\u0c28\u0c3f\u0c15\u0c3f \u0c21\u0c46\u0c32\u0c3f\u0c35\u0c30\u0c40 \u0c24\u0c4d\u0c35\u0c30\u0c32\u0c4b \u0c35\u0c38\u0c4d\u0c24\u0c41\u0c02\u0c26\u0c3f!",
    exp_desc: "\u0c2a\u0c4d\u0c30\u0c38\u0c4d\u0c24\u0c41\u0c24\u0c02 \u0c39\u0c28\u0c41\u0c2e\u0c15\u0c4a\u0c02\u0c21 \u0c2e\u0c30\u0c3f\u0c2f\u0c41 \u0c35\u0c30\u0c02\u0c17\u0c32\u0c4d \u0c28\u0c17\u0c30\u0c3e\u0c32\u0c15\u0c41 \u0c2e\u0c3e\u0c24\u0c4d\u0c30\u0c2e\u0c47 \u0c21\u0c46\u0c32\u0c3f\u0c35\u0c30\u0c40 \u0c1a\u0c47\u0c38\u0c4d\u0c24\u0c41\u0c28\u0c4d\u0c28\u0c3e\u0c2e\u0c41. 15-20 \u0c15\u0c41\u0c1f\u0c41\u0c02\u0c2c\u0c3e\u0c32\u0c41 \u0c06\u0c38\u0c15\u0c4d\u0c24\u0c3f \u0c1a\u0c42\u0c2a\u0c3f\u0c38\u0c4d\u0c24\u0c47 \u0c2a\u0c4d\u0c30\u0c24\u0c4d\u0c2f\u0c47\u0c15 \u0c30\u0c42\u0c1f\u0c4d \u0c2a\u0c4d\u0c30\u0c3e\u0c30\u0c02\u0c2d\u0c3f\u0c38\u0c4d\u0c24\u0c3e\u0c2e\u0c41.",
    exp_threshold_msg: "\u0c2e\u0c40 \u0c35\u0c3f\u0c35\u0c30\u0c3e\u0c32\u0c41 \u0c28\u0c2e\u0c4b\u0c26\u0c41 \u0c1a\u0c47\u0c2f\u0c02\u0c21\u0c3f.",
    btn_join_waitlist: "\ud83d\udccb \u0c35\u0c46\u0c2f\u0c3f\u0c1f\u0c4d\u0c32\u0c3f\u0c38\u0c4d\u0c1f\u0c4d\u0c32\u0c4b \u0c1a\u0c47\u0c30\u0c02\u0c21\u0c3f",
    exp_already_joined: "\u2713 \u0c2e\u0c40\u0c30\u0c41 \u0c07\u0c2a\u0c4d\u0c2a\u0c1f\u0c3f\u0c15\u0c47 \u0c28\u0c2e\u0c4b\u0c26\u0c2f\u0c4d\u0c2f\u0c3e\u0c30\u0c41.",
  },

  en: {
    // -------------------------------------------------------------------------
    // Brand
    // -------------------------------------------------------------------------
    brand_title: "Prakruthi Siri",
    brand_subtitle: "Chemical-Free Organic Vegetables Delivered to Your Doorstep",

    // -------------------------------------------------------------------------
    // Onboarding / Mobile Check-In
    // -------------------------------------------------------------------------
    onboarding_badge: "Mobile Check-In",
    onboarding_hint: "Enter your 10-digit mobile number to unlock today's fresh harvest catalog.",
    btn_continue: "Continue →",

    // -------------------------------------------------------------------------
    // Returning Customer
    // -------------------------------------------------------------------------
    returning_badge: "Your Profile",
    returning_welcome: "Welcome back,",
    btn_change_mobile: "Change Mobile",
    lbl_select_address: "Select Delivery Address",
    btn_confirm_address: "✓ Confirm Address & View Catalog →",
    btn_add_new_address: "+ Add New Address",
    gps_pinned_badge: "📍 GPS Pinned",

    // -------------------------------------------------------------------------
    // Inline New Address Form
    // -------------------------------------------------------------------------
    inline_addr_title: "Add New Delivery Address",
    lbl_addr_label: "Label (e.g. Home, Office)",
    placeholder_addr_label: "Home",
    lbl_addr_street: "Street Address *",
    placeholder_addr_street: "Flat/House No, Colony or Street",
    lbl_addr_landmark: "Landmark (Optional)",
    placeholder_addr_landmark: "e.g. Near Ganesh Temple",
    gps_section_title: "📍 GPS Doorstep Location",
    btn_use_gps: "📍 Use Current GPS",
    btn_pin_map: "🗺️ Pin on Map",
    placeholder_maps_link: "Paste WhatsApp / Google Maps link",
    map_hint: "Use GPS or pin your doorstep on the map.",
    map_drag_hint: "Drag pin or tap map to set doorstep coordinates",
    lbl_addr_city: "City / Locality *",
    btn_save_address: "Save & Use Address",

    // -------------------------------------------------------------------------
    // New Customer Form
    // -------------------------------------------------------------------------
    new_cust_header: "New Customer Details",
    new_cust_badge: "First-Time Setup",
    lbl_new_name: "Full Name *",
    placeholder_new_name: "e.g. Ramesh Sharma",
    lbl_new_address: "Delivery Address *",
    placeholder_new_address: "Flat/House No, Apartment or Street",
    lbl_new_landmark: "Landmark (Optional)",
    placeholder_new_landmark: "e.g. Opposite Ganesh Temple",
    gps_section_desc: "Pin your doorstep location for faster morning delivery",
    lbl_new_locality: "Select City *",
    kazipet_note: "Kazipet is outside our current delivery zone.",
    btn_save_proceed: "Save & Proceed to Vegetables →",

    // -------------------------------------------------------------------------
    // Confirmed Address Strip
    // -------------------------------------------------------------------------
    lbl_delivering_to: "Delivering To",
    btn_change_address: "Change",

    // -------------------------------------------------------------------------
    // Locked Catalog Card
    // -------------------------------------------------------------------------
    locked_title: "Fresh Organic Harvest",
    locked_desc: "Enter your 10-digit mobile number to view today's fresh vegetable catalog.",
    locked_badge: "⚡ Mobile check-in unlocks batch slots & harvest catalog",

    // -------------------------------------------------------------------------
    // Batch / Delivery Selector
    // -------------------------------------------------------------------------
    badge_delivery_batch: "Delivery Batch",
    title_delivery_batch: "Select Delivery Area",
    title_schedule_locked: "Your Delivery Schedule",
    lbl_delivering_to_locked: "Delivering to your area:",
    lbl_auto_locked_badge: "✓ Auto-Locked to Address",
    bookings_open: "Bookings Open",
    bookings_closed: "Bookings Closed",
    bookings_upcoming: "Booking Opens Soon",
    lbl_next_delivery: "Delivery Date:",
    lbl_cutoff_time: "Booking Closes:",
    batch_capacity_label: "Batch Capacity:",
    batch_slots_booked: function(n) { return "Bookings Full"; },
    batch_full_msg: "Batch Full. Cart disabled for this batch.",
    hanamkonda_label: "Hanamkonda",
    warangal_label: "Warangal",

    // -------------------------------------------------------------------------
    // Step 2: Vegetable Catalog
    // -------------------------------------------------------------------------
    badge_step2: "Step 2",
    title_step2: "Farm-Fresh Harvest Selection",
    unit_per_half_kg: "/ 0.5 kg",
    sold_out: "Sold Out",
    available_stock: "Available",
    varieties_label: function(n) { return n + " varieties"; },
    catalog_empty: "No fresh vegetables available for this batch.",

    // -------------------------------------------------------------------------
    // Sticky Cart & Drawer
    // -------------------------------------------------------------------------
    basket_label: "Your Vegetable Basket",
    cart_count: function(n, kg) { return "(" + n + " packets \u2022 " + kg + " kg)"; },
    btn_review_order: "Review & Checkout →",
    drawer_title: "Order Review & Checkout",
    drawer_basket_heading: "Items in Basket",
    cart_empty: "Your cart is empty",
    cart_item_qty: function(qty, kg) { return qty + " packets (" + kg + " kg)"; },

    // Drawer address card
    lbl_drawer_address: "Delivery Address",
    btn_drawer_change: "Change",

    // Checkout form labels
    name_label: "Full Name *",
    name_placeholder: "e.g. Ramesh Sharma",
    phone_label: "10-Digit Mobile Number *",
    phone_placeholder: "9876543210",
    address_label: "House / Flat No, Street, Colony *",
    address_placeholder: "Flat 302, Sri Sai Residency, Main Road",
    landmark_label: "Landmark (Optional)",
    landmark_placeholder: "e.g. Opposite Ganesh Temple",
    locality_label: "Delivery Area *",

    // Payment
    payment_mode_label: "Payment Method *",
    pay_cod_label: "Cash on Delivery (COD)",
    pay_cod_sub: "Pay cash at doorstep",
    pay_upi_label: "Online UPI",
    pay_upi_sub: "GPay / PhonePe / Paytm",

    // Bill breakdown
    items_subtotal: "Items Subtotal:",
    delivery_fee: "Delivery Fee:",
    delivery_free: "FREE",
    mov_alert: function(shortfall) { return "Add \u20b9" + shortfall + " more for FREE delivery!"; },
    total_payable: "Total Amount Payable:",

    // Submit
    btn_confirm_order: "Confirm & Place Order →",
    placing_order: "Placing your order...",
    batch_full_btn: "Batch Full",

    // -------------------------------------------------------------------------
    // Alerts & Validations
    // -------------------------------------------------------------------------
    empty_basket_alert: "Your basket is empty. Please select vegetables first.",
    phone_invalid_alert: "Please enter a valid 10-digit mobile number.",
    name_required_alert: "Please enter your full name.",
    address_required_alert: "Please enter your delivery address.",
    select_address_alert: "Please select a delivery address.",
    street_required_alert: "Please enter the street address.",
    stock_limit_alert: function(name, stock) { return name + ": Only " + stock + " packets available."; },
    batch_full_alert: "Sorry! This delivery batch is full.",
    gps_locating: "Locating...",
    gps_latched: "✓ GPS Latched",
    gps_unsupported: "Geolocation is not supported by your browser.",
    gps_failed: "Could not acquire GPS. Paste a Maps link or pin on the map.",
    saving_order: "Saving Order...",

    // -------------------------------------------------------------------------
    // Order Receipt (order-success.php)
    // -------------------------------------------------------------------------
    receipt_title: "Order Placed Successfully!",
    receipt_subtitle: "Thank you! Fresh chemical-free organic vegetables will be delivered to your doorstep.",
    receipt_delivery_label: "Scheduled Delivery:",
    receipt_order_ref: "Order Reference",
    receipt_payment_mode: "Payment",
    receipt_delivering_to: "Delivering To",
    receipt_basket_heading: "Items in Your Basket",
    receipt_packet_unit: function(pkts, kg) { return pkts + " packets (" + kg + " kg)"; },
    receipt_subtotal: "Items Subtotal:",
    receipt_delivery_fee: "Delivery Fee:",
    receipt_fee_free: "FREE",
    receipt_grand_total: "Total Payable:",
    receipt_paid_cod: "Cash on Delivery (COD)",
    receipt_paid_upi: "Online UPI",
    btn_send_whatsapp: "💬 Send Details on WhatsApp",
    btn_track_order: "📍 Track Order Live →",
    btn_shop_more: "← Browse More Vegetables",
    receipt_home_link: "← Home",

    // -------------------------------------------------------------------------
    // Live Order Tracker (track.php)
    // -------------------------------------------------------------------------
    tracker_tagline: "Live Order Tracker",
    tracker_store_link: "Store →",
    tracker_progress_title: "Live Progress Timeline",
    tracker_step1_title: "Order Received",
    tracker_step1_desc: "Confirmed & added to farm dispatch queue.",
    tracker_step2_title: "Harvested & Packed",
    tracker_step2_desc: "Vegetables harvested fresh and packed for delivery.",
    tracker_step3_title: "Out for Delivery",
    tracker_step3_desc: "Driver dispatched to your doorstep.",
    tracker_step4_title: "Delivered at Doorstep",
    tracker_step4_desc: "Doorstep delivery complete.",
    tracker_cancelled_msg: "This order has been cancelled and inventory returned.",
    tracker_items_heading: "Items Ordered",
    tracker_subtotal: "Subtotal:",
    tracker_delivery_fee: "Delivery Fee:",
    tracker_grand_total: "Total:",
    tracker_payment_label: "Payment:",
    tracker_delivery_batch: "Delivery Batch:",
    tracker_recipient: "Recipient:",
    tracker_address: "Address:",
    tracker_placed_at: "Placed:",
    btn_order_more: "+ Order More Fresh Vegetables",
    btn_whatsapp_support: "💬 WhatsApp Support",
    tracker_search_placeholder: "Enter Order Code (e.g. PS-1031)...",
    tracker_phone_placeholder: "10-digit mobile number...",
    tracker_track_btn: "Track",
    tracker_empty_state_title: "Track Your Order",
    tracker_empty_state_desc: "Enter your order code to view real-time delivery status.",
    tracker_item_qty: function(pkts, kg) { return pkts + " pkts (" + kg + " kg)"; },
    tracker_eta_heading: "Estimated Delivery Window",
    tracker_queue_heading: "Your Queue Position",
    tracker_queue_waiting: function(seq, total) { return "Your order is Stop " + seq + " of " + total; },
    tracker_queue_active: function(driverStop, mySeq) { return "Driver is currently at Stop " + driverStop + "; your delivery is Stop " + mySeq; },
    tracker_eta_window: function(day, start, end) { return (day ? day + " between " : "Between ") + start + " – " + end; },

    // PWA
    pwa_banner_text: "Add Prakruthi Siri to your Home Screen for easy ordering.",

    modal_region_title: "Select Your Delivery Area",
    modal_region_desc: "We deliver to Hanamkonda and Warangal. Choose your area to see today's harvest.",
    modal_region_note: "Kazipet is currently outside our delivery zone.",
    locked_cross_title: "Orders Not Open for Your Area Yet",
    locked_cross_desc: function(a,b,c,d){return a+" orders are open now. "+c+" orders open on "+d+".";},
    locked_cross_opens_label: function(r,f){return r+" opens: "+f;},
    locked_cross_wa_text: "\ud83d\udcac Remind Me on WhatsApp",
    locked_cross_change_btn: "Change Area",
    locked_closed_title: "Orders Closed for This Batch",
    locked_closed_desc: function(r,f){return r+" next batch opens on "+f+".";},
    locked_closed_badge: function(f){return "Next batch: "+f;},
    locked_closed_change_btn: "Change Area",
    exp_title: "Delivery Coming to Your Area Soon!",
    exp_desc: "We currently deliver only within Hanamkonda and Warangal. Once 15-20 households in your colony express interest, we will launch a dedicated route.",
    exp_threshold_msg: "Register your interest - we will notify you first when your area is added.",
    btn_join_waitlist: "\ud83d\udccb Join the Waitlist",
    exp_already_joined: "You are already on the waitlist.",
  }
};
