// =====================================================================
// assets/js/seller_invoice.js — AgriCart Seller Invoice page behaviour
// Handles: "Print Invoice" (native browser print of the on-screen HTML)
// and "Download PDF" (a real text-based PDF built with jsPDF directly
// from SINV_DATA — every string is drawn with pdf.text(), not a
// rasterised screenshot, so the result stays selectable/searchable).
// =====================================================================
(function () {
  'use strict';

  // -------------------------------------------------------------
  // 0. Translations — every data-sinv="key" element gets its text
  //    replaced, same mechanism as pages/invoice.js (data-agi) so the
  //    Seller Invoice supports the same EN/MR/HI switching as every
  //    other AgriCart page. Keep keys in sync with data-sinv attributes
  //    in seller/invoice.php.
  // -------------------------------------------------------------
  const SINV_I18N = {
    en: {
      backToDashboard: 'Back to Dashboard', printInvoice: 'Print Invoice', downloadPdf: 'Download PDF',
      sellerInvoiceTitle: 'SELLER INVOICE', invoiceNumber: 'Invoice No:', orderId: 'Order ID:',
      invoiceDate: 'Invoice Date:', orderDate: 'Order Date:', paymentLabel: 'Payment:', settlementLabel: 'Settlement:',
      sellerDetailsTitle: 'Seller / Company Details', gstinLabel: 'GSTIN / Tax ID:',
      buyerOrderDetailsTitle: 'Buyer & Order Details', deliveryAddressLabel: 'Delivery / Shipping Address',
      orderStatusLabel: 'Order Status:', productsSoldTitle: 'Products Sold',
      thSr: 'Sr.', thImage: 'Image', thProduct: 'Product', thSku: 'SKU', thQty: 'Qty', thUnitPrice: 'Unit Price',
      thDiscount: 'Discount', thProductValue: 'Product Value', thTax: 'Tax', thTotal: 'Total', thStatus: 'Status',
      reversedNote: 'Rows shown greyed out with a Cancelled/Returned/Refunded status are excluded from your earnings below — see Refund / Adjustment in the Settlement Summary for the total reversed.',
      financialBreakdownTitle: 'Financial Breakdown', productValue: 'Product Value', applicableTaxes: 'Applicable Taxes',
      grossOrderValue: 'Gross Order Value', platformCommission: 'Platform Commission', paymentGatewayFee: 'Payment Gateway Fee',
      notApplicable: 'Not applicable', otherPlatformCharges: 'Other Platform Charges', totalPlatformCharges: 'Total Platform Charges',
      settlementSummaryTitle: 'Seller Settlement Summary', grossProductValue: 'Gross Product Value',
      totalTaxesInfo: 'Total Taxes (informational, not deducted)', finalNetEarnings: 'Final Seller Net Earnings',
      amountCredited: 'Amount credited to your AgriCart wallet/balance — matches Earnings & Payouts',
      refundAdjustment: 'Refund / Adjustment (cancelled/returned items, already excluded above)',
      paymentInfoTitle: 'Payment Information', paymentMethodLabel: 'Payment Method:', paymentStatusLabel: 'Payment Status:',
      paidAmountLabel: 'Paid Amount:', transactionIdLabel: 'Transaction ID:', paymentGatewayLabel: 'Payment Gateway:',
      paymentDateLabel: 'Payment Date:', notesTitle: 'Notes',
      note1: 'This invoice reflects your earnings for this order only; it is not a tax invoice to the buyer.',
      note2: 'Platform charges are calculated automatically from your commission rate on file.',
      note3: 'Settlement status updates automatically as the order progresses through delivery and payout.',
      settlementDetailsTitle: 'Settlement Details', statusLabel: 'Status:', settlementRefLabel: 'Settlement Reference:',
      assignedOncePaid: 'Assigned once paid out', seeEarningsNote: 'See Earnings & Payouts in your Seller Dashboard for full payout history.',
      authorizedSignatoryTitle: 'Authorized Signatory', signStampArea: 'Digital Signature / Seller Stamp', forLabel: 'For',
      computerGenerated: 'This is a computer-generated seller invoice and does not require a physical signature.',
      poweredByLabel: 'Powered by',
    },
    mr: {
      backToDashboard: 'डॅशबोर्डवर परत जा', printInvoice: 'पावती प्रिंट करा', downloadPdf: 'PDF डाउनलोड करा',
      sellerInvoiceTitle: 'विक्रेता पावती', invoiceNumber: 'पावती क्र.:', orderId: 'ऑर्डर आयडी:',
      invoiceDate: 'पावती दिनांक:', orderDate: 'ऑर्डर दिनांक:', paymentLabel: 'पेमेंट:', settlementLabel: 'सेटलमेंट:',
      sellerDetailsTitle: 'विक्रेता / कंपनी तपशील', gstinLabel: 'जीएसटीआयएन / कर आयडी:',
      buyerOrderDetailsTitle: 'खरेदीदार आणि ऑर्डर तपशील', deliveryAddressLabel: 'डिलिव्हरी / शिपिंग पत्ता',
      orderStatusLabel: 'ऑर्डर स्थिती:', productsSoldTitle: 'विकलेली उत्पादने',
      thSr: 'अ.क्र.', thImage: 'छायाचित्र', thProduct: 'उत्पादन', thSku: 'एसकेयू', thQty: 'प्रमाण', thUnitPrice: 'दर',
      thDiscount: 'सूट', thProductValue: 'उत्पादन मूल्य', thTax: 'कर', thTotal: 'एकूण', thStatus: 'स्थिती',
      reversedNote: 'रद्द/परत/रिफंड झालेल्या ओळी फिकट दिसतात व त्या खालील कमाईतून वगळल्या आहेत — एकूण रकमेसाठी सेटलमेंट सारांशातील Refund/Adjustment पहा.',
      financialBreakdownTitle: 'आर्थिक तपशील', productValue: 'उत्पादन मूल्य', applicableTaxes: 'लागू कर',
      grossOrderValue: 'एकूण ऑर्डर मूल्य', platformCommission: 'प्लॅटफॉर्म कमिशन', paymentGatewayFee: 'पेमेंट गेटवे शुल्क',
      notApplicable: 'लागू नाही', otherPlatformCharges: 'इतर प्लॅटफॉर्म शुल्क', totalPlatformCharges: 'एकूण प्लॅटफॉर्म शुल्क',
      settlementSummaryTitle: 'विक्रेता सेटलमेंट सारांश', grossProductValue: 'एकूण उत्पादन मूल्य',
      totalTaxesInfo: 'एकूण कर (केवळ माहितीसाठी, वजा केलेले नाही)', finalNetEarnings: 'अंतिम निव्वळ कमाई',
      amountCredited: 'तुमच्या AgriCart वॉलेट/शिल्लक मध्ये जमा होणारी रक्कम — कमाई व पेआउट्सशी जुळते',
      refundAdjustment: 'रिफंड / समायोजन (रद्द/परत केलेल्या वस्तू, आधीच वगळलेल्या)',
      paymentInfoTitle: 'पेमेंट माहिती', paymentMethodLabel: 'पेमेंट पद्धत:', paymentStatusLabel: 'पेमेंट स्थिती:',
      paidAmountLabel: 'भरलेली रक्कम:', transactionIdLabel: 'व्यवहार आयडी:', paymentGatewayLabel: 'पेमेंट गेटवे:',
      paymentDateLabel: 'पेमेंट दिनांक:', notesTitle: 'सूचना',
      note1: 'हे पावती फक्त या ऑर्डरसाठी तुमची कमाई दर्शवते; हे खरेदीदारासाठीचे कर बीजक नाही.',
      note2: 'प्लॅटफॉर्म शुल्क तुमच्या नोंदणीकृत कमिशन दरानुसार आपोआप मोजले जाते.',
      note3: 'डिलिव्हरी व पेआउट प्रगतीनुसार सेटलमेंट स्थिती आपोआप अद्ययावत होते.',
      settlementDetailsTitle: 'सेटलमेंट तपशील', statusLabel: 'स्थिती:', settlementRefLabel: 'सेटलमेंट संदर्भ:',
      assignedOncePaid: 'पैसे दिल्यावर नियुक्त केले जाईल', seeEarningsNote: 'संपूर्ण पेआउट इतिहासासाठी तुमच्या सेलर डॅशबोर्डमधील कमाई व पेआउट्स पहा.',
      authorizedSignatoryTitle: 'अधिकृत स्वाक्षरीकर्ता', signStampArea: 'डिजिटल स्वाक्षरी / विक्रेता शिक्का', forLabel: 'यांच्यासाठी',
      computerGenerated: 'हे संगणकाद्वारे तयार केलेले विक्रेता पावती आहे आणि त्यासाठी प्रत्यक्ष स्वाक्षरीची आवश्यकता नाही.',
      poweredByLabel: 'द्वारा समर्थित',
    },
    hi: {
      backToDashboard: 'डैशबोर्ड पर वापस जाएं', printInvoice: 'चालान प्रिंट करें', downloadPdf: 'PDF डाउनलोड करें',
      sellerInvoiceTitle: 'विक्रेता चालान', invoiceNumber: 'चालान सं.:', orderId: 'ऑर्डर आईडी:',
      invoiceDate: 'चालान दिनांक:', orderDate: 'ऑर्डर दिनांक:', paymentLabel: 'भुगतान:', settlementLabel: 'निपटान:',
      sellerDetailsTitle: 'विक्रेता / कंपनी विवरण', gstinLabel: 'जीएसटीआईएन / कर आईडी:',
      buyerOrderDetailsTitle: 'खरीदार और ऑर्डर विवरण', deliveryAddressLabel: 'डिलीवरी / शिपिंग पता',
      orderStatusLabel: 'ऑर्डर स्थिति:', productsSoldTitle: 'बेचे गए उत्पाद',
      thSr: 'क्र.', thImage: 'छवि', thProduct: 'उत्पाद', thSku: 'एसकेयू', thQty: 'मात्रा', thUnitPrice: 'दर',
      thDiscount: 'छूट', thProductValue: 'उत्पाद मूल्य', thTax: 'कर', thTotal: 'कुल', thStatus: 'स्थिति',
      reversedNote: 'रद्द/वापस/रिफंड की गई पंक्तियाँ धुंधली दिखती हैं और आपकी कमाई से बाहर रखी गई हैं — कुल राशि के लिए निपटान सारांश में Refund/Adjustment देखें।',
      financialBreakdownTitle: 'वित्तीय विवरण', productValue: 'उत्पाद मूल्य', applicableTaxes: 'लागू कर',
      grossOrderValue: 'सकल ऑर्डर मूल्य', platformCommission: 'प्लेटफॉर्म कमीशन', paymentGatewayFee: 'भुगतान गेटवे शुल्क',
      notApplicable: 'लागू नहीं', otherPlatformCharges: 'अन्य प्लेटफॉर्म शुल्क', totalPlatformCharges: 'कुल प्लेटफॉर्म शुल्क',
      settlementSummaryTitle: 'विक्रेता निपटान सारांश', grossProductValue: 'सकल उत्पाद मूल्य',
      totalTaxesInfo: 'कुल कर (केवल सूचना हेतु, घटाया नहीं गया)', finalNetEarnings: 'अंतिम शुद्ध कमाई',
      amountCredited: 'आपके AgriCart वॉलेट/बैलेंस में जमा होने वाली राशि — कमाई और भुगतान से मेल खाती है',
      refundAdjustment: 'रिफंड / समायोजन (रद्द/वापस किए गए आइटम, पहले से बाहर रखे गए)',
      paymentInfoTitle: 'भुगतान जानकारी', paymentMethodLabel: 'भुगतान का तरीका:', paymentStatusLabel: 'भुगतान स्थिति:',
      paidAmountLabel: 'भुगतान की गई राशि:', transactionIdLabel: 'लेन-देन आईडी:', paymentGatewayLabel: 'भुगतान गेटवे:',
      paymentDateLabel: 'भुगतान दिनांक:', notesTitle: 'सूचनाएं',
      note1: 'यह चालान केवल इस ऑर्डर के लिए आपकी कमाई दर्शाता है; यह खरीदार के लिए कर चालान नहीं है।',
      note2: 'प्लेटफॉर्म शुल्क आपकी दर्ज कमीशन दर के अनुसार स्वतः गणना किया जाता है।',
      note3: 'डिलीवरी और भुगतान की प्रगति के अनुसार निपटान स्थिति स्वतः अपडेट होती है।',
      settlementDetailsTitle: 'निपटान विवरण', statusLabel: 'स्थिति:', settlementRefLabel: 'निपटान संदर्भ:',
      assignedOncePaid: 'भुगतान होने पर निर्धारित किया जाएगा', seeEarningsNote: 'पूर्ण भुगतान इतिहास के लिए अपने सेलर डैशबोर्ड में कमाई और भुगतान देखें।',
      authorizedSignatoryTitle: 'अधिकृत हस्ताक्षरकर्ता', signStampArea: 'डिजिटल हस्ताक्षर / विक्रेता मुहर', forLabel: 'की ओर से',
      computerGenerated: 'यह कंप्यूटर-जनित विक्रेता चालान है और इसके लिए भौतिक हस्ताक्षर की आवश्यकता नहीं है।',
      poweredByLabel: 'द्वारा संचालित',
    },
  };

  function sinvApplyLang(lang) {
    if (!SINV_I18N[lang]) lang = 'en';
    const dict = SINV_I18N[lang];
    document.querySelectorAll('[data-sinv]').forEach((el) => {
      const key = el.getAttribute('data-sinv');
      if (dict[key]) el.textContent = dict[key];
    });
    // Product names are stored per-language on the element itself (server
    // only knows what's in the DB) — pick the right one, falling back to
    // English so a name never goes blank just because a translation is
    // missing for one product. Same mechanism as pages/invoice.js.
    document.querySelectorAll('[data-sinv-prod-name]').forEach((el) => {
      const val = el.getAttribute('data-name-' + lang) || el.getAttribute('data-name-en') || '';
      if (val) el.textContent = val;
    });
  }

  function sinvInitLang() {
    // Same site-wide language selector as everywhere else (includes/header.php),
    // stored in localStorage under 'agri_lang'. Hooks into
    // switchLanguage()'s pageLanguageCallback so the invoice updates
    // immediately if the person changes language from the header.
    let saved = 'en';
    try { saved = localStorage.getItem('agri_lang') || window.AGI_DEFAULT_LANG || 'en'; }
    catch (e) { saved = window.AGI_DEFAULT_LANG || 'en'; }
    sinvApplyLang(saved);
    window.pageLanguageCallback = sinvApplyLang;
  }

  function money(n) {
    n = Number(n) || 0;
    return 'Rs. ' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function sinvInitPrint() {
    const btn = document.getElementById('sinvPrintBtn');
    if (btn) btn.addEventListener('click', () => window.print());
  }

  function drawInvoicePdf(pdf, data) {
    let lang = 'en';
    try { lang = localStorage.getItem('agri_lang') || window.AGI_DEFAULT_LANG || 'en'; }
    catch (e) { lang = window.AGI_DEFAULT_LANG || 'en'; }
    if (!SINV_I18N[lang]) lang = 'en';
    const t = SINV_I18N[lang];

    const marginX = 15;
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const contentWidth = pageWidth - marginX * 2;
    let y = 18;

    function ensureSpace(need) {
      if (y + need > pageHeight - 18) {
        pdf.addPage();
        y = 18;
      }
    }
    function hr(yy) {
      pdf.setDrawColor(210, 220, 210);
      pdf.line(marginX, yy, pageWidth - marginX, yy);
    }
    function sectionTitle(text) {
      ensureSpace(12);
      pdf.setFont('helvetica', 'bold');
      pdf.setFontSize(10.5);
      pdf.setTextColor(17, 43, 17);
      pdf.text(text.toUpperCase(), marginX, y);
      y += 2;
      hr(y);
      y += 6;
    }
    function kv(label, value, xOffset) {
      xOffset = xOffset || 0;
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(9.5);
      pdf.setTextColor(90, 90, 90);
      pdf.text(String(label), marginX + xOffset, y);
      pdf.setTextColor(20, 20, 20);
      pdf.setFont('helvetica', 'bold');
      pdf.text(String(value || '-'), marginX + xOffset + 38, y);
      y += 5.5;
    }

    // ---- Header ----
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(16);
    pdf.setTextColor(17, 43, 17);
    pdf.text(data.seller.name || 'AgriCart Seller', marginX, y);
    pdf.setFontSize(9);
    pdf.setTextColor(110, 110, 110);
    pdf.text('Seller ID: #' + data.seller.seller_id, marginX, y + 5.5);

    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(13);
    pdf.setTextColor(17, 43, 17);
    pdf.text(t.sellerInvoiceTitle, pageWidth - marginX, y, { align: 'right' });
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(9);
    pdf.setTextColor(70, 70, 70);
    const metaLines = [
      t.invoiceNumber + ' ' + data.invoice_no,
      t.orderId + ' ' + data.order_code,
      t.invoiceDate + ' ' + data.invoice_date,
      t.orderDate + ' ' + data.order_date,
      t.paymentLabel + ' ' + data.payment_status + '   |   ' + t.settlementLabel + ' ' + data.settlement_status,
    ];
    metaLines.forEach((line, i) => pdf.text(line, pageWidth - marginX, y + 6 + i * 4.5, { align: 'right' }));
    y += 32;
    hr(y);
    y += 8;

    // ---- Seller / Buyer two columns ----
    const colWidth = contentWidth / 2 - 4;
    const startY = y;
    pdf.setFont('helvetica', 'bold'); pdf.setFontSize(9.5); pdf.setTextColor(46, 125, 50);
    pdf.text(t.sellerDetailsTitle.toUpperCase(), marginX, y);
    pdf.setFont('helvetica', 'bold'); pdf.setFontSize(9.5); pdf.setTextColor(46, 125, 50);
    pdf.text(t.buyerOrderDetailsTitle.toUpperCase(), marginX + colWidth + 8, y);
    y += 6;

    const sellerLines = [data.seller.name, data.seller.mobile, data.seller.email, data.seller.address, data.seller.gstin ? t.gstinLabel + ' ' + data.seller.gstin : ''].filter(Boolean);
    const buyerLines = [data.buyer.name, data.buyer.mobile, data.buyer.email, data.buyer.address, t.orderStatusLabel + ' ' + data.order_status].filter(Boolean);
    const maxLines = Math.max(sellerLines.length, buyerLines.length);
    pdf.setFont('helvetica', 'normal'); pdf.setFontSize(9); pdf.setTextColor(30, 30, 30);
    for (let i = 0; i < maxLines; i++) {
      if (sellerLines[i]) pdf.text(pdf.splitTextToSize(sellerLines[i], colWidth), marginX, y);
      if (buyerLines[i]) pdf.text(pdf.splitTextToSize(buyerLines[i], colWidth), marginX + colWidth + 8, y);
      y += 5;
    }
    y = Math.max(y, startY + 6 * maxLines) + 6;

    // ---- Products table ----
    sectionTitle(t.productsSoldTitle);
    const cols = [
      { key: 'name', label: t.thProduct, w: 42, align: 'left' },
      { key: 'sku', label: t.thSku, w: 18, align: 'left' },
      { key: 'qty', label: t.thQty, w: 12, align: 'right' },
      { key: 'price', label: t.thUnitPrice, w: 22, align: 'right' },
      { key: 'discount', label: t.thDiscount, w: 20, align: 'right' },
      { key: 'tax', label: t.thTax, w: 18, align: 'right' },
      { key: 'total', label: t.thTotal, w: 22, align: 'right' },
      { key: 'status', label: t.thStatus, w: 26, align: 'left' },
    ];
    function tableHeader() {
      pdf.setFillColor(17, 43, 17);
      pdf.rect(marginX, y - 4.5, contentWidth, 6.5, 'F');
      pdf.setFont('helvetica', 'bold'); pdf.setFontSize(8); pdf.setTextColor(255, 255, 255);
      let x = marginX + 1.5;
      cols.forEach(c => { pdf.text(c.label, c.align === 'right' ? x + c.w - 2 : x, y, { align: c.align }); x += c.w; });
      y += 4;
    }
    tableHeader();
    pdf.setFont('helvetica', 'normal'); pdf.setFontSize(8);
    (data.items || []).forEach((it, idx) => {
      ensureSpace(8);
      if (y < 20) tableHeader();
      if (idx % 2 === 1) { pdf.setFillColor(250, 251, 250); pdf.rect(marginX, y - 4, contentWidth, 5.5, 'F'); }
      let x = marginX + 1.5;
      const cells = {
        name: (lang === 'mr' && it.name_mr) || (lang === 'hi' && it.name_hi) || it.name,
        sku: it.sku, qty: String(it.qty), price: money(it.price),
        discount: it.discount > 0 ? money(it.discount) : '-', tax: money(it.tax),
        total: money(it.total), status: it.status,
      };
      pdf.setTextColor(it.status && it.status.toLowerCase() !== 'confirmed' && it.status.toLowerCase() !== 'delivered' && it.status.toLowerCase() !== 'new order' ? 150 : 20, 20, 20);
      cols.forEach(c => {
        const val = pdf.splitTextToSize(String(cells[c.key] ?? '-'), c.w - 2)[0] || '';
        pdf.text(val, c.align === 'right' ? x + c.w - 2 : x, y, { align: c.align });
        x += c.w;
      });
      y += 5.5;
    });
    y += 4;

    // ---- Financial breakdown ----
    sectionTitle(t.financialBreakdownTitle + ' & ' + t.settlementSummaryTitle);
    const f = data.financials;
    kv(t.productValue, money(f.gross_product_value));
    kv(t.applicableTaxes, money(f.total_tax));
    kv(t.grossOrderValue, money(f.gross_order_value));
    y += 2;
    kv(t.platformCommission + ' (' + f.platform_commission_percent + '%)', money(f.platform_commission_amount));
    kv(t.totalPlatformCharges, money(f.total_platform_charges));
    if (Math.abs(f.refund_adjustment) > 0.001) {
      kv(t.refundAdjustment, money(Math.abs(f.refund_adjustment)));
    }
    y += 2;
    ensureSpace(14);
    pdf.setFillColor(232, 245, 233);
    pdf.rect(marginX, y - 5.5, contentWidth, 11, 'F');
    pdf.setFont('helvetica', 'bold'); pdf.setFontSize(11); pdf.setTextColor(17, 43, 17);
    pdf.text(t.finalNetEarnings.toUpperCase(), marginX + 3, y + 1);
    pdf.setFontSize(13); pdf.setTextColor(46, 125, 50);
    pdf.text(money(f.net_earnings), pageWidth - marginX - 3, y + 1.5, { align: 'right' });
    y += 14;

    // ---- Payment info ----
    sectionTitle(t.paymentInfoTitle);
    kv(t.paymentMethodLabel.replace(':', ''), data.payment.method);
    kv(t.paymentStatusLabel.replace(':', ''), data.payment.status);
    kv(t.transactionIdLabel.replace(':', ''), data.payment.transaction_id);
    kv(t.paymentGatewayLabel.replace(':', ''), data.payment.gateway);
    kv(t.paidAmountLabel.replace(':', ''), money(data.payment.paid_amount));

    // ---- Footer ----
    ensureSpace(16);
    y = pageHeight - 14;
    hr(y - 4);
    pdf.setFont('helvetica', 'normal'); pdf.setFontSize(8); pdf.setTextColor(120, 120, 120);
    pdf.text(t.computerGenerated, pageWidth / 2, y, { align: 'center' });
    pdf.setFont('helvetica', 'bold'); pdf.setFontSize(9); pdf.setTextColor(90, 152, 2);
    pdf.text(t.poweredByLabel + ' AgriCart', pageWidth / 2, y + 5, { align: 'center' });
  }

  function sinvInitPdf() {
    const btn = document.getElementById('sinvPdfBtn');
    if (!btn) return;
    btn.addEventListener('click', () => {
      if (!window.jspdf || typeof SINV_DATA === 'undefined') {
        alert('PDF library failed to load. Please check your connection and try again.');
        return;
      }
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating…';
      try {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        drawInvoicePdf(pdf, SINV_DATA);
        const safeNo = String(SINV_DATA.invoice_no || 'invoice').replace(/[^a-zA-Z0-9-]/g, '');
        pdf.save(`AgriCart-Seller-Invoice-${safeNo}.pdf`);
      } catch (err) {
        console.error('AgriCart seller invoice PDF generation failed:', err);
        alert('Sorry, the PDF could not be generated. Please try again or use Print Invoice instead.');
      } finally {
        btn.disabled = false;
        btn.innerHTML = original;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    sinvInitLang();
    sinvInitPrint();
    sinvInitPdf();
  });
})();
