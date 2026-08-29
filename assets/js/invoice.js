// =====================================================================
// pages/invoice.js — AgriCart Invoice page behaviour
// Handles: EN/MR label translation, "Print Invoice", "Download PDF".
// Loaded by invoice.php after html2canvas + jsPDF from CDN.
// =====================================================================
(function () {
  'use strict';

  // -------------------------------------------------------------
  // 1. Translations — every data-agi="key" element gets its text
  //    replaced. Keep keys in sync with the data-agi attributes in
  //    invoice.php.
  // -------------------------------------------------------------
  const AGI_I18N = {
    en: {
      backToOrders: 'Back to Orders',
      printInvoice: 'Print Invoice',
      downloadPdf: 'Download PDF',
      taxInvoice: 'TAX INVOICE',
      salesInvoice: 'PRODUCT INVOICE',
      invoiceNumber: 'Invoice No:',
      orderId: 'Order ID:',
      invoiceDate: 'Invoice Date:',
      orderDate: 'Order Date:',
      billTo: 'Bill To',
      soldBy: 'Sold By',
      deliveryAddrLabel: 'Delivery Address',
      gstin: 'GSTIN:',
      sellerId: 'Seller ID:',
      multipleSellers: 'AgriCart Marketplace (multiple sellers)',
      seePerItem: 'See the seller listed against each product below.',
      thSr: 'Sr.', thImage: 'Image', thProduct: 'Product', thCategory: 'Category',
      thQty: 'Qty', thUnitPrice: 'Unit Price', thDiscount: 'Discount', thGst: 'GST/Tax', thTotal: 'Total',
      soldByShort: 'Sold by',
      subtotal: 'Product Subtotal',
      productDiscount: 'Product Discount',
      couponDiscount: 'Coupon Discount',
      deliveryCharges: 'Delivery Charges',
      free: 'Free',
      gstTax: 'GST/Tax',
      roundOff: 'Round Off',
      grandTotal: 'Grand Total',
      amountPaid: 'Amount Paid',
      remaining: 'Remaining Amount',
      paymentInfo: 'Payment Information',
      paymentMethod: 'Payment Method',
      transactionId: 'Transaction ID',
      paymentDate: 'Payment Date',
      paymentStatusLbl: 'Payment Status',
      notes: 'Notes',
      noteReturn: "Products are subject to AgriCart's return and refund policy.",
      noteKeep: 'Keep this invoice for returns, warranty and customer support.',
      noteVary: 'Product colours or packaging may vary slightly.',
      customerSupport: 'Customer Support',
      supportHours: 'Mon–Sat, 9:00 AM – 7:00 PM',
      authorizedSignatory: 'Authorized Signatory',
      signStampArea: 'Digital Signature / Seller Stamp',
      forAgricart: 'For AgriCart Marketplace',
      thankYou: 'Thank you for shopping with AgriCart.',
      computerGenerated: 'This is a computer-generated invoice and does not require a physical signature.',
      returnPolicy: 'Return Policy',
      refundPolicy: 'Refund Policy',
      terms: 'Terms and Conditions',
      privacy: 'Privacy Policy',
    },
    mr: {
      backToOrders: 'ऑर्डरकडे परत जा',
      printInvoice: 'बीजक प्रिंट करा',
      downloadPdf: 'PDF डाउनलोड करा',
      taxInvoice: 'कर बीजक',
      salesInvoice: 'उत्पादन बीजक',
      invoiceNumber: 'बीजक क्रमांक:',
      orderId: 'ऑर्डर आयडी:',
      invoiceDate: 'बीजक दिनांक:',
      orderDate: 'ऑर्डर दिनांक:',
      billTo: 'ग्राहक माहिती',
      soldBy: 'विक्रेता माहिती',
      deliveryAddrLabel: 'डिलिव्हरी पत्ता',
      gstin: 'जीएसटीआयएन:',
      sellerId: 'विक्रेता आयडी:',
      multipleSellers: 'अ‍ॅग्रीकार्ट मार्केटप्लेस (अनेक विक्रेते)',
      seePerItem: 'प्रत्येक उत्पादनासमोर नमूद केलेला विक्रेता पहा.',
      thSr: 'अ.क्र.', thImage: 'छायाचित्र', thProduct: 'उत्पादन', thCategory: 'प्रकार',
      thQty: 'प्रमाण', thUnitPrice: 'दर', thDiscount: 'सूट', thGst: 'जीएसटी/कर', thTotal: 'एकूण',
      soldByShort: 'विक्रेता',
      subtotal: 'उत्पादन उपबेरीज',
      productDiscount: 'उत्पादन सूट',
      couponDiscount: 'कूपन सूट',
      deliveryCharges: 'डिलिव्हरी शुल्क',
      free: 'मोफत',
      gstTax: 'जीएसटी/कर',
      roundOff: 'राउंड ऑफ',
      grandTotal: 'एकूण रक्कम',
      amountPaid: 'भरलेली रक्कम',
      remaining: 'शिल्लक रक्कम',
      paymentInfo: 'पेमेंट माहिती',
      paymentMethod: 'पेमेंट पद्धत',
      transactionId: 'व्यवहार आयडी',
      paymentDate: 'पेमेंट दिनांक',
      paymentStatusLbl: 'पेमेंट स्थिती',
      notes: 'सूचना',
      noteReturn: 'उत्पादने अ‍ॅग्रीकार्टच्या परतावा आणि रिफंड धोरणाच्या अधीन आहेत.',
      noteKeep: 'परतावा, वॉरंटी आणि ग्राहक सेवेसाठी हे बीजक जपून ठेवा.',
      noteVary: 'उत्पादनाचा रंग किंवा पॅकेजिंग थोडेफार वेगळे असू शकते.',
      customerSupport: 'ग्राहक सेवा',
      supportHours: 'सोम–शनि, सकाळी ९:०० — सायं ७:००',
      authorizedSignatory: 'अधिकृत स्वाक्षरीकर्ता',
      signStampArea: 'डिजिटल स्वाक्षरी / विक्रेता शिक्का',
      forAgricart: 'अ‍ॅग्रीकार्ट मार्केटप्लेस तर्फे',
      thankYou: 'अ‍ॅग्रीकार्टसोबत खरेदी केल्याबद्दल धन्यवाद.',
      computerGenerated: 'हे संगणकाद्वारे तयार केलेले बीजक आहे आणि त्यासाठी प्रत्यक्ष स्वाक्षरीची आवश्यकता नाही.',
      returnPolicy: 'परतावा धोरण',
      refundPolicy: 'रिफंड धोरण',
      terms: 'अटी व शर्ती',
      privacy: 'गोपनीयता धोरण',
    },
    hi: {
      backToOrders: 'ऑर्डर पर वापस जाएं',
      printInvoice: 'इनवॉइस प्रिंट करें',
      downloadPdf: 'PDF डाउनलोड करें',
      taxInvoice: 'कर चालान',
      salesInvoice: 'उत्पाद चालान',
      invoiceNumber: 'चालान संख्या:',
      orderId: 'ऑर्डर आईडी:',
      invoiceDate: 'चालान दिनांक:',
      orderDate: 'ऑर्डर दिनांक:',
      billTo: 'ग्राहक विवरण',
      soldBy: 'विक्रेता विवरण',
      deliveryAddrLabel: 'डिलीवरी पता',
      gstin: 'जीएसटीआईएन:',
      sellerId: 'विक्रेता आईडी:',
      multipleSellers: 'एग्रीकार्ट मार्केटप्लेस (कई विक्रेता)',
      seePerItem: 'हर उत्पाद के सामने दिया गया विक्रेता देखें.',
      thSr: 'क्र.', thImage: 'छवि', thProduct: 'उत्पाद', thCategory: 'श्रेणी',
      thQty: 'मात्रा', thUnitPrice: 'दर', thDiscount: 'छूट', thGst: 'जीएसटी/कर', thTotal: 'कुल',
      soldByShort: 'विक्रेता',
      subtotal: 'उत्पाद उप-योग',
      productDiscount: 'उत्पाद छूट',
      couponDiscount: 'कूपन छूट',
      deliveryCharges: 'डिलीवरी शुल्क',
      free: 'मुफ्त',
      gstTax: 'जीएसटी/कर',
      roundOff: 'राउंड ऑफ',
      grandTotal: 'कुल राशि',
      amountPaid: 'भुगतान की गई राशि',
      remaining: 'शेष राशि',
      paymentInfo: 'भुगतान जानकारी',
      paymentMethod: 'भुगतान का तरीका',
      transactionId: 'लेन-देन आईडी',
      paymentDate: 'भुगतान दिनांक',
      paymentStatusLbl: 'भुगतान स्थिति',
      notes: 'सूचनाएं',
      noteReturn: 'उत्पाद एग्रीकार्ट की वापसी और रिफंड नीति के अधीन हैं.',
      noteKeep: 'वापसी, वारंटी और ग्राहक सहायता के लिए यह चालान सुरक्षित रखें.',
      noteVary: 'उत्पाद का रंग या पैकेजिंग थोड़ा भिन्न हो सकता है.',
      customerSupport: 'ग्राहक सहायता',
      supportHours: 'सोम–शनि, सुबह 9:00 — शाम 7:00',
      authorizedSignatory: 'अधिकृत हस्ताक्षरकर्ता',
      signStampArea: 'डिजिटल हस्ताक्षर / विक्रेता मुहर',
      forAgricart: 'एग्रीकार्ट मार्केटप्लेस की ओर से',
      thankYou: 'एग्रीकार्ट के साथ खरीदारी करने के लिए धन्यवाद.',
      computerGenerated: 'यह कंप्यूटर-जनित चालान है और इसके लिए भौतिक हस्ताक्षर की आवश्यकता नहीं है.',
      returnPolicy: 'वापसी नीति',
      refundPolicy: 'रिफंड नीति',
      terms: 'नियम व शर्तें',
      privacy: 'गोपनीयता नीति',
    },
  };

  function agiApplyLang(lang) {
    if (!AGI_I18N[lang]) lang = 'en';
    const dict = AGI_I18N[lang];
    document.querySelectorAll('[data-agi]').forEach((el) => {
      const key = el.getAttribute('data-agi');
      if (dict[key]) el.textContent = dict[key];
    });
    // Product names are stored per-language on the element itself (server
    // only knows what's in the DB) — pick the right one, falling back to
    // English so a name never goes blank just because a translation is
    // missing for one product.
    document.querySelectorAll('[data-agi-prod-name]').forEach((el) => {
      const val = el.getAttribute('data-name-' + lang) || el.getAttribute('data-name-en') || '';
      if (val) el.textContent = val;
    });
  }

  function agiInitLang() {
    // The site's header (includes/header.php) owns the language selector and
    // stores the choice in localStorage under 'agri_lang'. We just read that
    // on load, and hook into switchLanguage()'s pageLanguageCallback so the
    // invoice updates immediately when someone changes it from the header —
    // no separate language control on this page.
    let saved = 'en';
    try { saved = localStorage.getItem('agri_lang') || window.AGI_DEFAULT_LANG || 'en'; }
    catch (e) { saved = window.AGI_DEFAULT_LANG || 'en'; }
    agiApplyLang(saved);
    window.pageLanguageCallback = agiApplyLang;
  }

  // -------------------------------------------------------------
  // 2. Print — the @media print rules in invoice.php already hide
  //    everything except .agi-print-area, so this is just a trigger.
  // -------------------------------------------------------------
  function agiInitPrint() {
    const btn = document.getElementById('agiPrintBtn');
    if (btn) btn.addEventListener('click', () => window.print());
  }

  // -------------------------------------------------------------
  // 3. PDF export via html2canvas + jsPDF, scaled to fit one A4 page.
  // -------------------------------------------------------------
  function agiInitPdf() {
    const btn = document.getElementById('agiPdfBtn');
    if (!btn) return;
    btn.addEventListener('click', async () => {
      if (typeof html2canvas === 'undefined' || !window.jspdf) {
        alert('PDF library failed to load. Please check your connection and try again.');
        return;
      }
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating…';

      try {
        const sheet = document.getElementById('agiInvoiceSheet');
        const canvas = await html2canvas(sheet, {
          scale: 2,
          useCORS: true,
          backgroundColor: '#ffffff',
        });

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();

        const imgWidth = pageWidth;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;

        const imgData = canvas.toDataURL('image/png');

        if (imgHeight <= pageHeight) {
          // Fits on a single page.
          pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
        } else {
          // Slice the tall canvas across as many A4 pages as needed,
          // so content never gets cut mid-row.
          let heightLeft = imgHeight;
          let position = 0;
          pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
          heightLeft -= pageHeight;
          while (heightLeft > 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
          }
        }

        const fileName = `AgriCart-Invoice-${(window.AGI_INVOICE_NO || 'invoice').replace(/[^a-zA-Z0-9-]/g, '')}.pdf`;
        pdf.save(fileName);
      } catch (err) {
        console.error('AgriCart invoice PDF generation failed:', err);
        alert('Sorry, the PDF could not be generated. Please try again or use Print Invoice instead.');
      } finally {
        btn.disabled = false;
        btn.innerHTML = original;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    agiInitLang();
    agiInitPrint();
    agiInitPdf();
  });
})();
