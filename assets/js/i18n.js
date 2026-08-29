/**
 * AgriCart shared translation dictionary (EN / HI / MR).
 * Used by both return-policy.php and refund-policy.php.
 * In production, merge these keys into the site's real i18n
 * JSON/translation system — keep the same key names.
 */
(function (global) {
  "use strict";

  var translations = {
    en: {
      "a11y.skip": "Skip to content",
      "nav.home": "Home", "nav.marketplace": "Marketplace", "nav.rentals": "Rentals", "nav.support": "Support",
      "nav.returnPolicy": "Return Policy", "nav.refundPolicy": "Refund Policy",

      "hero.return.eyebrow": "Buyer & Seller Protection",
      "hero.return.title": "Return Policy",
      "hero.return.subtitle": "Understand how to request a return or replacement for products and rental equipment.",
      "hero.return.pill1": "7-Day Product Returns", "hero.return.pill2": "24-Hr Rental Reporting", "hero.return.pill3": "Easy Pickup Process",

      "hero.refund.eyebrow": "Money-Back Guarantee",
      "hero.refund.title": "Refund Policy",
      "hero.refund.subtitle": "Understand how and when refunds are processed once your return is approved.",
      "hero.refund.pill1": "3–7 Day Refunds", "hero.refund.pill2": "Multiple Refund Methods", "hero.refund.pill3": "Inspection-Based Approval",

      "intro.return.eyebrow": "Introduction",
      "intro.return.title": "Our Commitment to Fair Returns",
      "intro.return.text": "AgriCart is committed to complete customer satisfaction while ensuring fairness for every seller on our platform. This Return Policy protects genuine buyers from damaged, incorrect or defective products, while safeguarding sellers from misuse of the return process. Every return and replacement request is reviewed transparently, in line with agricultural product handling standards and rental equipment best practices.",

      "intro.refund.eyebrow": "Introduction",
      "intro.refund.title": "How Refunds Work at AgriCart",
      "intro.refund.text": "Once a return is approved and the product passes quality inspection, AgriCart processes your refund quickly and transparently to your original payment method or a preferred option. This Refund Policy explains the refund process, timelines and available payment methods.",

      "eligibility.eyebrow": "Eligibility", "eligibility.title": "Return Eligibility",
      "eligibility.desc": "Check whether your order qualifies for a return before submitting a request.",
      "eligibility.eligibleTitle": "Eligible for Return",
      "eligibility.eligible1": "Product damaged during delivery", "eligibility.eligible2": "Wrong product received",
      "eligibility.eligible3": "Missing items in the order", "eligibility.eligible4": "Manufacturing defect",
      "eligibility.eligible5": "Product not matching description",
      "eligibility.notEligibleTitle": "Not Eligible for Return",
      "eligibility.notEligible1": "Used products", "eligibility.notEligible2": "Damaged by the customer",
      "eligibility.notEligible3": "Return request after the allowed period", "eligibility.notEligible4": "Perishable agricultural goods",
      "eligibility.notEligible5": "Customized products",

      "window.eyebrow": "Timelines", "window.title": "Return Window",
      "window.products.title": "Products", "window.products.value": "Return within 7 Days", "window.products.desc": "Starting from the date of delivery",
      "window.rental.title": "Rental Equipment", "window.rental.value": "Report issues within 24 Hours", "window.rental.desc": "Of receiving or noticing the issue",
      "window.replacement.title": "Replacement", "window.replacement.value": "Subject to seller approval", "window.replacement.desc": "Reviewed on a case-by-case basis",

      "refundProcess.eyebrow": "How It Works", "refundProcess.title": "Refund Process",
      "refundProcess.step1.title": "Submit Return Request", "refundProcess.step1.desc": "Raise a request from your order history with reason and photos.",
      "refundProcess.step2.title": "Seller Verification", "refundProcess.step2.desc": "The seller reviews your request and supporting evidence.",
      "refundProcess.step3.title": "Pickup Scheduled", "refundProcess.step3.desc": "A pickup is arranged at your registered delivery address.",
      "refundProcess.step4.title": "Quality Inspection", "refundProcess.step4.desc": "The returned item is inspected to confirm the reported issue.",
      "refundProcess.step5.title": "Refund Processed", "refundProcess.step5.desc": "Your refund is initiated to the original payment method.",
      "refundProcess.note": "Refunds are processed only after successful quality inspection of the returned item.",

      "refundMethods.eyebrow": "Refund Methods", "refundMethods.title": "Get Refunded Your Way",
      "refundMethods.method1": "Original Payment Method", "refundMethods.method2": "UPI",
      "refundMethods.method3": "Bank Transfer", "refundMethods.method4": "Wallet Balance (if applicable)",
      "refundMethods.timeValue": "3–7 Business Days", "refundMethods.timeLabel": "Estimated refund processing time",

      "rental.eyebrow": "Rental Equipment", "rental.title": "Rental Equipment Policy",
      "rental.text": "Rental agreements follow a separate set of conditions to protect both farmers and equipment owners on AgriCart.",
      "rental.point1": "Equipment must be returned in the agreed condition.",
      "rental.point2": "Late return charges may apply beyond the booked period.",
      "rental.point3": "Security deposit is refunded after inspection.",
      "rental.point4": "Damage charges may be deducted from the deposit if necessary.",

      "seller.eyebrow": "For Sellers", "seller.title": "Seller Responsibility",
      "seller.point1": "Provide genuine, quality-checked products", "seller.point2": "Pack products securely for transit",
      "seller.point3": "Respond promptly to return requests", "seller.point4": "Approve valid refunds without delay",

      "buyer.eyebrow": "For Buyers", "buyer.title": "Buyer Responsibility",
      "buyer.point1": "Check the product immediately after delivery", "buyer.point2": "Upload proof if the product is damaged",
      "buyer.point3": "Return the product with original packaging", "buyer.point4": "Avoid misuse of the return policy",

      "cancellation.eyebrow": "Cancellations", "cancellation.title": "Cancellation Policy",
      "cancellation.before.title": "Before Shipping", "cancellation.before.desc": "The order can be cancelled freely before it has been shipped.",
      "cancellation.after.title": "After Shipping", "cancellation.after.desc": "Cancellation depends on seller approval once the order is in transit.",
      "cancellation.rentalBooking.title": "Rental Booking", "cancellation.rentalBooking.desc": "Cancellation rules depend on the current status of the rental booking.",

      "faq.return.eyebrow": "FAQ", "faq.return.title": "Frequently Asked Questions",
      "faq.return.q1": "How do I request a return?",
      "faq.return.a1": "Go to your Order History, select the item and choose \"Request Return.\" Add a reason and photos, then submit for seller review.",
      "faq.return.q2": "Can I return rental equipment?",
      "faq.return.a2": "Rental equipment follows the rental agreement terms. Report any issues within 24 hours of receiving the equipment.",
      "faq.return.q3": "What if the seller rejects my return?",
      "faq.return.a3": "If you disagree with a rejection, you can escalate the request to AgriCart Support for an independent review.",
      "faq.return.q4": "Who pays for return shipping?",
      "faq.return.a4": "If the return is due to a seller or delivery error, AgriCart covers the shipping cost. Otherwise, it is borne by the buyer.",
      "faq.return.q5": "What is the return window for products?",
      "faq.return.a5": "Most products can be returned within 7 days of delivery, provided they meet the eligibility criteria listed above.",

      "faq.refund.eyebrow": "FAQ", "faq.refund.title": "Frequently Asked Questions",
      "faq.refund.q1": "How long does the refund take?",
      "faq.refund.a1": "Once the returned item passes inspection, refunds are processed within 3–7 business days to your original payment method.",
      "faq.refund.q2": "What refund methods are available?",
      "faq.refund.a2": "Refunds can be issued to your original payment method, UPI, bank transfer, or wallet balance where applicable.",
      "faq.refund.q3": "Is my rental security deposit refundable?",
      "faq.refund.a3": "Yes. The deposit is refunded after equipment inspection, minus any applicable late-return or damage charges.",
      "faq.refund.q4": "What if I haven't received my refund?",
      "faq.refund.a4": "If your refund hasn't arrived after 7 business days, please contact our Customer Support team for assistance.",
      "faq.refund.q5": "Can my refund be sent to a different account?",
      "faq.refund.a5": "Refunds are generally issued to the original payment method. Contact Support if you need an alternate arrangement.",

      "help.title": "Need Help?", "help.subtitle": "Our customer support team is here for any return or refund questions.",
      "help.emailLabel": "Email", "help.phoneLabel": "Phone", "help.hoursLabel": "Support Hours",
      "help.hoursValue": "Mon–Sat, 9:00 AM – 7:00 PM",
      "help.rightText": "Still have questions about a return, replacement, or rental refund? Reach out and our team will get back to you.",
      "help.contactBtn": "Contact Us",

      "crosslink.toRefund.eyebrow": "Related",
      "crosslink.toRefund.text": "Looking for details on how and when your money is refunded?",
      "crosslink.toRefund.btn": "View Refund Policy",
      "crosslink.toReturn.eyebrow": "Related",
      "crosslink.toReturn.text": "Need to start a return or replacement request first?",
      "crosslink.toReturn.btn": "View Return Policy",

      "footer.disclaimerLabel": "Disclaimer:", "footer.disclaimer": "AgriCart reserves the right to modify this policy at any time.",
      "footer.lastUpdatedLabel": "Last Updated:", "footer.rights": "All rights reserved.",
      "footer.tagline": "Enterprise Sustainable Agritech Portal"
    },

    hi: {
      "a11y.skip": "मुख्य सामग्री पर जाएं",
      "nav.home": "होम", "nav.marketplace": "मार्केटप्लेस", "nav.rentals": "किराया सेवाएं", "nav.support": "सहायता",
      "nav.returnPolicy": "वापसी नीति", "nav.refundPolicy": "रिफंड नीति",

      "hero.return.eyebrow": "खरीदार और विक्रेता सुरक्षा",
      "hero.return.title": "वापसी नीति",
      "hero.return.subtitle": "उत्पादों और किराये के उपकरणों के लिए वापसी या प्रतिस्थापन का अनुरोध कैसे करें, यह समझें।",
      "hero.return.pill1": "7 दिन में उत्पाद वापसी", "hero.return.pill2": "24 घंटे में किराया शिकायत", "hero.return.pill3": "आसान पिकअप प्रक्रिया",

      "hero.refund.eyebrow": "पैसा वापसी की गारंटी",
      "hero.refund.title": "रिफंड नीति",
      "hero.refund.subtitle": "आपकी वापसी स्वीकृत होने के बाद रिफंड कैसे और कब प्रोसेस किया जाता है, यह समझें।",
      "hero.refund.pill1": "3–7 दिन में रिफंड", "hero.refund.pill2": "कई रिफंड तरीके", "hero.refund.pill3": "निरीक्षण आधारित स्वीकृति",

      "intro.return.eyebrow": "परिचय", "intro.return.title": "निष्पक्ष वापसी के प्रति हमारी प्रतिबद्धता",
      "intro.return.text": "AgriCart अपने प्लेटफ़ॉर्म पर हर विक्रेता के लिए निष्पक्षता सुनिश्चित करते हुए पूर्ण ग्राहक संतुष्टि के लिए प्रतिबद्ध है। यह वापसी नीति वास्तविक खरीदारों को क्षतिग्रस्त, गलत या दोषपूर्ण उत्पादों से बचाती है, साथ ही विक्रेताओं को वापसी प्रक्रिया के दुरुपयोग से भी सुरक्षित रखती है। हर वापसी और प्रतिस्थापन अनुरोध की समीक्षा कृषि उत्पाद प्रबंधन मानकों और किराये के उपकरण की सर्वोत्तम प्रथाओं के अनुरूप पारदर्शी रूप से की जाती है।",

      "intro.refund.eyebrow": "परिचय", "intro.refund.title": "AgriCart पर रिफंड कैसे काम करता है",
      "intro.refund.text": "वापसी स्वीकृत होने और उत्पाद के गुणवत्ता निरीक्षण में पास होने के बाद, AgriCart आपके मूल भुगतान माध्यम या पसंदीदा विकल्प में तुरंत और पारदर्शी रूप से रिफंड प्रोसेस करता है। यह रिफंड नीति प्रक्रिया, समय-सीमा और उपलब्ध भुगतान माध्यमों के बारे में बताती है।",

      "eligibility.eyebrow": "पात्रता", "eligibility.title": "वापसी पात्रता",
      "eligibility.desc": "अनुरोध सबमिट करने से पहले जांचें कि आपका ऑर्डर वापसी के योग्य है या नहीं।",
      "eligibility.eligibleTitle": "वापसी के लिए योग्य",
      "eligibility.eligible1": "डिलीवरी के दौरान उत्पाद क्षतिग्रस्त होना", "eligibility.eligible2": "गलत उत्पाद प्राप्त होना",
      "eligibility.eligible3": "ऑर्डर में सामान गायब होना", "eligibility.eligible4": "निर्माण दोष",
      "eligibility.eligible5": "उत्पाद विवरण से मेल न खाना",
      "eligibility.notEligibleTitle": "वापसी के लिए अयोग्य",
      "eligibility.notEligible1": "इस्तेमाल किए गए उत्पाद", "eligibility.notEligible2": "ग्राहक द्वारा क्षतिग्रस्त",
      "eligibility.notEligible3": "निर्धारित अवधि के बाद वापसी अनुरोध", "eligibility.notEligible4": "शीघ्र खराब होने वाली कृषि वस्तुएं",
      "eligibility.notEligible5": "अनुकूलित (कस्टमाइज़्ड) उत्पाद",

      "window.eyebrow": "समय-सीमा", "window.title": "वापसी अवधि",
      "window.products.title": "उत्पाद", "window.products.value": "7 दिनों के भीतर वापसी", "window.products.desc": "डिलीवरी की तारीख से गणना",
      "window.rental.title": "किराये के उपकरण", "window.rental.value": "24 घंटे के भीतर शिकायत करें", "window.rental.desc": "प्राप्ति या समस्या पहचानने से",
      "window.replacement.title": "प्रतिस्थापन", "window.replacement.value": "विक्रेता की स्वीकृति के अधीन", "window.replacement.desc": "प्रत्येक मामले की अलग से समीक्षा",

      "refundProcess.eyebrow": "यह कैसे काम करता है", "refundProcess.title": "रिफंड प्रक्रिया",
      "refundProcess.step1.title": "वापसी अनुरोध सबमिट करें", "refundProcess.step1.desc": "अपने ऑर्डर इतिहास से कारण और फ़ोटो के साथ अनुरोध करें।",
      "refundProcess.step2.title": "विक्रेता सत्यापन", "refundProcess.step2.desc": "विक्रेता आपके अनुरोध और सबूतों की समीक्षा करता है।",
      "refundProcess.step3.title": "पिकअप निर्धारित", "refundProcess.step3.desc": "आपके पंजीकृत डिलीवरी पते पर पिकअप की व्यवस्था की जाती है।",
      "refundProcess.step4.title": "गुणवत्ता निरीक्षण", "refundProcess.step4.desc": "रिपोर्ट की गई समस्या की पुष्टि हेतु उत्पाद का निरीक्षण किया जाता है।",
      "refundProcess.step5.title": "रिफंड प्रक्रिया पूर्ण", "refundProcess.step5.desc": "आपका रिफंड मूल भुगतान माध्यम में शुरू किया जाता है।",
      "refundProcess.note": "रिफंड केवल वापस किए गए उत्पाद के सफल गुणवत्ता निरीक्षण के बाद ही संसाधित किया जाता है।",

      "refundMethods.eyebrow": "रिफंड के तरीके", "refundMethods.title": "अपनी पसंद के अनुसार रिफंड पाएं",
      "refundMethods.method1": "मूल भुगतान माध्यम", "refundMethods.method2": "UPI",
      "refundMethods.method3": "बैंक ट्रांसफर", "refundMethods.method4": "वॉलेट बैलेंस (यदि लागू हो)",
      "refundMethods.timeValue": "3–7 कार्य दिवस", "refundMethods.timeLabel": "अनुमानित रिफंड प्रोसेसिंग समय",

      "rental.eyebrow": "किराये के उपकरण", "rental.title": "किराये के उपकरण नीति",
      "rental.text": "किराये के समझौते AgriCart पर किसानों और उपकरण मालिकों दोनों की सुरक्षा के लिए अलग शर्तों का पालन करते हैं।",
      "rental.point1": "उपकरण सहमत स्थिति में वापस किया जाना चाहिए।",
      "rental.point2": "बुक की गई अवधि के बाद देर से वापसी पर शुल्क लागू हो सकता है।",
      "rental.point3": "निरीक्षण के बाद सुरक्षा जमा राशि वापस की जाती है।",
      "rental.point4": "आवश्यक होने पर क्षति शुल्क जमा राशि से काटा जा सकता है।",

      "seller.eyebrow": "विक्रेताओं के लिए", "seller.title": "विक्रेता की जिम्मेदारी",
      "seller.point1": "असली, गुणवत्ता-जांचे गए उत्पाद उपलब्ध कराएं", "seller.point2": "परिवहन के लिए उत्पादों की सुरक्षित पैकिंग करें",
      "seller.point3": "वापसी अनुरोधों का तुरंत जवाब दें", "seller.point4": "वैध रिफंड बिना देरी के स्वीकृत करें",

      "buyer.eyebrow": "खरीदारों के लिए", "buyer.title": "खरीदार की जिम्मेदारी",
      "buyer.point1": "डिलीवरी के तुरंत बाद उत्पाद की जांच करें", "buyer.point2": "क्षतिग्रस्त होने पर प्रमाण अपलोड करें",
      "buyer.point3": "मूल पैकेजिंग के साथ उत्पाद वापस करें", "buyer.point4": "वापसी नीति का दुरुपयोग न करें",

      "cancellation.eyebrow": "रद्दीकरण", "cancellation.title": "रद्दीकरण नीति",
      "cancellation.before.title": "शिपिंग से पहले", "cancellation.before.desc": "शिप होने से पहले ऑर्डर स्वतंत्र रूप से रद्द किया जा सकता है।",
      "cancellation.after.title": "शिपिंग के बाद", "cancellation.after.desc": "ऑर्डर ट्रांज़िट में होने पर रद्दीकरण विक्रेता की स्वीकृति पर निर्भर करता है।",
      "cancellation.rentalBooking.title": "किराया बुकिंग", "cancellation.rentalBooking.desc": "रद्दीकरण के नियम किराया बुकिंग की वर्तमान स्थिति पर निर्भर करते हैं।",

      "faq.return.eyebrow": "सामान्य प्रश्न", "faq.return.title": "अक्सर पूछे जाने वाले प्रश्न",
      "faq.return.q1": "मैं वापसी का अनुरोध कैसे करूं?",
      "faq.return.a1": "अपने ऑर्डर इतिहास में जाएं, उत्पाद चुनें और \"वापसी का अनुरोध करें\" चुनें। कारण और फ़ोटो जोड़कर विक्रेता समीक्षा के लिए सबमिट करें।",
      "faq.return.q2": "क्या मैं किराये के उपकरण वापस कर सकता हूं?",
      "faq.return.a2": "किराये के उपकरण किराया समझौते की शर्तों का पालन करते हैं। उपकरण प्राप्त होने के 24 घंटे के भीतर किसी भी समस्या की रिपोर्ट करें।",
      "faq.return.q3": "अगर विक्रेता मेरी वापसी को अस्वीकार कर दे तो क्या होगा?",
      "faq.return.a3": "यदि आप अस्वीकृति से असहमत हैं, तो आप स्वतंत्र समीक्षा के लिए अनुरोध को AgriCart सहायता तक बढ़ा सकते हैं।",
      "faq.return.q4": "वापसी शिपिंग का भुगतान कौन करता है?",
      "faq.return.a4": "यदि वापसी विक्रेता या डिलीवरी की गलती के कारण है, तो AgriCart शिपिंग लागत वहन करता है। अन्यथा, यह खरीदार द्वारा वहन की जाती है।",
      "faq.return.q5": "उत्पादों के लिए वापसी अवधि क्या है?",
      "faq.return.a5": "अधिकांश उत्पाद डिलीवरी के 7 दिनों के भीतर वापस किए जा सकते हैं, बशर्ते वे ऊपर दी गई पात्रता शर्तों को पूरा करते हों।",

      "faq.refund.eyebrow": "सामान्य प्रश्न", "faq.refund.title": "अक्सर पूछे जाने वाले प्रश्न",
      "faq.refund.q1": "रिफंड में कितना समय लगता है?",
      "faq.refund.a1": "वापस किए गए उत्पाद के निरीक्षण में पास होने के बाद, रिफंड आपके मूल भुगतान माध्यम में 3–7 कार्य दिवसों के भीतर संसाधित किया जाता है।",
      "faq.refund.q2": "कौन से रिफंड तरीके उपलब्ध हैं?",
      "faq.refund.a2": "रिफंड आपके मूल भुगतान माध्यम, UPI, बैंक ट्रांसफर, या लागू होने पर वॉलेट बैलेंस में जारी किया जा सकता है।",
      "faq.refund.q3": "क्या मेरी किराये की सुरक्षा जमा राशि वापसी योग्य है?",
      "faq.refund.a3": "हां। उपकरण निरीक्षण के बाद, लागू देर से वापसी या क्षति शुल्क घटाकर जमा राशि वापस की जाती है।",
      "faq.refund.q4": "अगर मुझे रिफंड नहीं मिला तो क्या करें?",
      "faq.refund.a4": "यदि 7 कार्य दिवसों के बाद भी आपका रिफंड नहीं आया है, तो कृपया सहायता के लिए हमारी ग्राहक सहायता टीम से संपर्क करें।",
      "faq.refund.q5": "क्या मेरा रिफंड किसी अन्य खाते में भेजा जा सकता है?",
      "faq.refund.a5": "रिफंड आमतौर पर मूल भुगतान माध्यम में जारी किया जाता है। वैकल्पिक व्यवस्था के लिए सहायता से संपर्क करें।",

      "help.title": "मदद चाहिए?", "help.subtitle": "किसी भी वापसी या रिफंड प्रश्न के लिए हमारी ग्राहक सहायता टीम उपलब्ध है।",
      "help.emailLabel": "ईमेल", "help.phoneLabel": "फ़ोन", "help.hoursLabel": "सहायता समय",
      "help.hoursValue": "सोम–शनि, सुबह 9:00 – शाम 7:00",
      "help.rightText": "वापसी, प्रतिस्थापन या किराया रिफंड के बारे में अभी भी प्रश्न हैं? संपर्क करें, हमारी टीम आपसे जल्द संपर्क करेगी।",
      "help.contactBtn": "संपर्क करें",

      "crosslink.toRefund.eyebrow": "संबंधित",
      "crosslink.toRefund.text": "जानना चाहते हैं कि आपका पैसा कैसे और कब वापस किया जाता है?",
      "crosslink.toRefund.btn": "रिफंड नीति देखें",
      "crosslink.toReturn.eyebrow": "संबंधित",
      "crosslink.toReturn.text": "पहले वापसी या प्रतिस्थापन अनुरोध शुरू करना है?",
      "crosslink.toReturn.btn": "वापसी नीति देखें",

      "footer.disclaimerLabel": "अस्वीकरण:", "footer.disclaimer": "AgriCart किसी भी समय इस नीति में बदलाव करने का अधिकार सुरक्षित रखता है।",
      "footer.lastUpdatedLabel": "अंतिम अद्यतन:", "footer.rights": "सर्वाधिकार सुरक्षित।",
      "footer.tagline": "एंटरप्राइज़ सस्टेनेबल एग्रीटेक पोर्टल"
    },

    mr: {
      "a11y.skip": "मुख्य मजकुराकडे जा",
      "nav.home": "मुख्यपृष्ठ", "nav.marketplace": "मार्केटप्लेस", "nav.rentals": "भाडे सेवा", "nav.support": "सहाय्य",
      "nav.returnPolicy": "परतावा धोरण", "nav.refundPolicy": "रिफंड धोरण",

      "hero.return.eyebrow": "खरेदीदार आणि विक्रेता संरक्षण",
      "hero.return.title": "परतावा धोरण",
      "hero.return.subtitle": "उत्पादने आणि भाड्याने दिलेल्या उपकरणांसाठी परतावा किंवा बदलीची विनंती कशी करावी हे समजून घ्या.",
      "hero.return.pill1": "7 दिवसांत उत्पादन परतावा", "hero.return.pill2": "24 तासांत भाडे तक्रार", "hero.return.pill3": "सोपी पिकअप प्रक्रिया",

      "hero.refund.eyebrow": "पैसे परत मिळण्याची हमी",
      "hero.refund.title": "रिफंड धोरण",
      "hero.refund.subtitle": "तुमचा परतावा मंजूर झाल्यानंतर रिफंड कसा आणि केव्हा प्रक्रिया केला जातो हे समजून घ्या.",
      "hero.refund.pill1": "3–7 दिवसांत रिफंड", "hero.refund.pill2": "अनेक रिफंड पद्धती", "hero.refund.pill3": "तपासणी-आधारित मंजुरी",

      "intro.return.eyebrow": "परिचय", "intro.return.title": "निष्पक्ष परताव्यासाठी आमची बांधिलकी",
      "intro.return.text": "AgriCart आपल्या प्लॅटफॉर्मवरील प्रत्येक विक्रेत्यासाठी निष्पक्षता सुनिश्चित करताना पूर्ण ग्राहक समाधानासाठी वचनबद्ध आहे. हे परतावा धोरण खऱ्या खरेदीदारांना खराब झालेल्या, चुकीच्या किंवा दोषपूर्ण उत्पादनांपासून संरक्षण देते, तसेच विक्रेत्यांना परतावा प्रक्रियेच्या गैरवापरापासून सुरक्षित ठेवते. प्रत्येक परतावा आणि बदली विनंतीचे पुनरावलोकन कृषी उत्पादन हाताळणी मानके आणि भाडे उपकरणांच्या सर्वोत्तम पद्धतींनुसार पारदर्शकपणे केले जाते.",

      "intro.refund.eyebrow": "परिचय", "intro.refund.title": "AgriCart वर रिफंड कसे कार्य करते",
      "intro.refund.text": "परतावा मंजूर झाल्यानंतर आणि उत्पादन गुणवत्ता तपासणीत उत्तीर्ण झाल्यानंतर, AgriCart तुमचा रिफंड तुमच्या मूळ पेमेंट पद्धतीत किंवा पसंतीच्या पर्यायात लवकर आणि पारदर्शकपणे प्रक्रिया करते. हे रिफंड धोरण प्रक्रिया, कालमर्यादा आणि उपलब्ध पेमेंट पद्धती स्पष्ट करते.",

      "eligibility.eyebrow": "पात्रता", "eligibility.title": "परतावा पात्रता",
      "eligibility.desc": "विनंती सबमिट करण्यापूर्वी तुमची ऑर्डर परताव्यासाठी पात्र आहे का ते तपासा.",
      "eligibility.eligibleTitle": "परताव्यासाठी पात्र",
      "eligibility.eligible1": "डिलिव्हरीदरम्यान उत्पादन खराब होणे", "eligibility.eligible2": "चुकीचे उत्पादन मिळणे",
      "eligibility.eligible3": "ऑर्डरमध्ये वस्तू गहाळ असणे", "eligibility.eligible4": "उत्पादन दोष (मॅन्युफॅक्चरिंग डिफेक्ट)",
      "eligibility.eligible5": "उत्पादन वर्णनाशी न जुळणे",
      "eligibility.notEligibleTitle": "परताव्यासाठी अपात्र",
      "eligibility.notEligible1": "वापरलेली उत्पादने", "eligibility.notEligible2": "ग्राहकाकडून खराब झालेले",
      "eligibility.notEligible3": "निर्धारित कालावधीनंतर परतावा विनंती", "eligibility.notEligible4": "लवकर खराब होणारा शेतमाल",
      "eligibility.notEligible5": "सानुकूलित (कस्टमाइज्ड) उत्पादने",

      "window.eyebrow": "कालमर्यादा", "window.title": "परतावा कालावधी",
      "window.products.title": "उत्पादने", "window.products.value": "7 दिवसांच्या आत परतावा", "window.products.desc": "डिलिव्हरीच्या तारखेपासून मोजणी",
      "window.rental.title": "भाड्याने घेतलेली उपकरणे", "window.rental.value": "24 तासांच्या आत तक्रार नोंदवा", "window.rental.desc": "मिळाल्यापासून किंवा समस्या लक्षात आल्यापासून",
      "window.replacement.title": "बदली", "window.replacement.value": "विक्रेत्याच्या मंजुरीच्या अधीन", "window.replacement.desc": "प्रत्येक प्रकरणाचे स्वतंत्र पुनरावलोकन",

      "refundProcess.eyebrow": "हे कसे कार्य करते", "refundProcess.title": "रिफंड प्रक्रिया",
      "refundProcess.step1.title": "परतावा विनंती सबमिट करा", "refundProcess.step1.desc": "तुमच्या ऑर्डर इतिहासातून कारण आणि फोटोंसह विनंती करा.",
      "refundProcess.step2.title": "विक्रेता पडताळणी", "refundProcess.step2.desc": "विक्रेता तुमच्या विनंतीचे आणि पुराव्यांचे पुनरावलोकन करतो.",
      "refundProcess.step3.title": "पिकअप नियोजित", "refundProcess.step3.desc": "तुमच्या नोंदणीकृत डिलिव्हरी पत्त्यावर पिकअपची व्यवस्था केली जाते.",
      "refundProcess.step4.title": "गुणवत्ता तपासणी", "refundProcess.step4.desc": "नोंदवलेल्या समस्येची पुष्टी करण्यासाठी उत्पादनाची तपासणी केली जाते.",
      "refundProcess.step5.title": "रिफंड प्रक्रिया पूर्ण", "refundProcess.step5.desc": "तुमचा रिफंड मूळ पेमेंट पद्धतीत सुरू केला जातो.",
      "refundProcess.note": "परत केलेल्या उत्पादनाच्या यशस्वी गुणवत्ता तपासणीनंतरच रिफंड प्रक्रिया केली जाते.",

      "refundMethods.eyebrow": "रिफंड पद्धती", "refundMethods.title": "तुमच्या पद्धतीने रिफंड मिळवा",
      "refundMethods.method1": "मूळ पेमेंट पद्धत", "refundMethods.method2": "UPI",
      "refundMethods.method3": "बँक ट्रान्सफर", "refundMethods.method4": "वॉलेट बॅलन्स (लागू असल्यास)",
      "refundMethods.timeValue": "3–7 कामकाजाचे दिवस", "refundMethods.timeLabel": "अंदाजित रिफंड प्रक्रिया वेळ",

      "rental.eyebrow": "भाड्याने दिलेली उपकरणे", "rental.title": "भाडे उपकरण धोरण",
      "rental.text": "AgriCart वर शेतकरी आणि उपकरण मालक दोघांच्याही संरक्षणासाठी भाडे करार वेगळ्या अटींनुसार चालतात.",
      "rental.point1": "उपकरण मान्य केलेल्या स्थितीत परत करणे आवश्यक आहे.",
      "rental.point2": "बुक केलेल्या कालावधीनंतर उशिरा परताव्यासाठी शुल्क लागू शकते.",
      "rental.point3": "तपासणीनंतर सुरक्षा ठेव परत केली जाते.",
      "rental.point4": "आवश्यक असल्यास नुकसान भरपाई ठेवीतून वजा केली जाऊ शकते.",

      "seller.eyebrow": "विक्रेत्यांसाठी", "seller.title": "विक्रेत्याची जबाबदारी",
      "seller.point1": "अस्सल, गुणवत्ता-तपासलेली उत्पादने पुरवा", "seller.point2": "वाहतुकीसाठी उत्पादने सुरक्षितपणे पॅक करा",
      "seller.point3": "परतावा विनंतींना त्वरित प्रतिसाद द्या", "seller.point4": "वैध रिफंड विलंब न करता मंजूर करा",

      "buyer.eyebrow": "खरेदीदारांसाठी", "buyer.title": "खरेदीदाराची जबाबदारी",
      "buyer.point1": "डिलिव्हरीनंतर लगेच उत्पादन तपासा", "buyer.point2": "नुकसान झाल्यास पुरावा अपलोड करा",
      "buyer.point3": "मूळ पॅकेजिंगसह उत्पादन परत करा", "buyer.point4": "परतावा धोरणाचा गैरवापर टाळा",

      "cancellation.eyebrow": "रद्दीकरण", "cancellation.title": "रद्दीकरण धोरण",
      "cancellation.before.title": "शिपिंगपूर्वी", "cancellation.before.desc": "शिपिंग होण्यापूर्वी ऑर्डर मुक्तपणे रद्द करता येते.",
      "cancellation.after.title": "शिपिंगनंतर", "cancellation.after.desc": "ऑर्डर वाहतुकीत असताना रद्दीकरण विक्रेत्याच्या मंजुरीवर अवलंबून असते.",
      "cancellation.rentalBooking.title": "भाडे बुकिंग", "cancellation.rentalBooking.desc": "रद्दीकरणाचे नियम भाडे बुकिंगच्या सद्यस्थितीवर अवलंबून असतात.",

      "faq.return.eyebrow": "वारंवार विचारले जाणारे प्रश्न", "faq.return.title": "वारंवार विचारले जाणारे प्रश्न",
      "faq.return.q1": "मी परतावा विनंती कशी करू?",
      "faq.return.a1": "तुमच्या ऑर्डर इतिहासात जा, वस्तू निवडा आणि \"परतावा विनंती करा\" निवडा. कारण आणि फोटो जोडून विक्रेत्याच्या पुनरावलोकनासाठी सबमिट करा.",
      "faq.return.q2": "मी भाड्याने घेतलेली उपकरणे परत करू शकतो का?",
      "faq.return.a2": "भाड्याने घेतलेली उपकरणे भाडे कराराच्या अटींनुसार असतात. उपकरण मिळाल्यापासून 24 तासांच्या आत कोणतीही समस्या नोंदवा.",
      "faq.return.q3": "विक्रेत्याने माझा परतावा नाकारला तर काय?",
      "faq.return.a3": "जर तुम्ही नकाराशी असहमत असाल, तर तुम्ही स्वतंत्र पुनरावलोकनासाठी विनंती AgriCart सहाय्यकडे वाढवू शकता.",
      "faq.return.q4": "परतावा शिपिंगचा खर्च कोण करतो?",
      "faq.return.a4": "जर परतावा विक्रेत्याच्या किंवा डिलिव्हरीच्या चुकीमुळे असेल, तर AgriCart शिपिंग खर्च उचलते. अन्यथा, तो खरेदीदाराने उचलावा लागतो.",
      "faq.return.q5": "उत्पादनांसाठी परतावा कालावधी किती आहे?",
      "faq.return.a5": "बहुतेक उत्पादने वरील पात्रता निकष पूर्ण केल्यास डिलिव्हरीच्या 7 दिवसांच्या आत परत करता येतात.",

      "faq.refund.eyebrow": "वारंवार विचारले जाणारे प्रश्न", "faq.refund.title": "वारंवार विचारले जाणारे प्रश्न",
      "faq.refund.q1": "रिफंडला किती वेळ लागतो?",
      "faq.refund.a1": "परत केलेले उत्पादन तपासणीत उत्तीर्ण झाल्यानंतर, रिफंड तुमच्या मूळ पेमेंट पद्धतीत 3–7 कामकाजाच्या दिवसांत प्रक्रिया केला जातो.",
      "faq.refund.q2": "कोणत्या रिफंड पद्धती उपलब्ध आहेत?",
      "faq.refund.a2": "रिफंड तुमच्या मूळ पेमेंट पद्धतीत, UPI, बँक ट्रान्सफर किंवा लागू असल्यास वॉलेट बॅलन्समध्ये दिला जाऊ शकतो.",
      "faq.refund.q3": "माझी भाडे सुरक्षा ठेव परत मिळण्यायोग्य आहे का?",
      "faq.refund.a3": "होय. उपकरण तपासणीनंतर, लागू उशिरा-परतावा किंवा नुकसान शुल्क वजा करून ठेव परत केली जाते.",
      "faq.refund.q4": "मला रिफंड मिळाला नाही तर काय करावे?",
      "faq.refund.a4": "जर 7 कामकाजाच्या दिवसांनंतरही तुमचा रिफंड आला नसेल, तर कृपया मदतीसाठी आमच्या ग्राहक सहाय्य टीमशी संपर्क साधा.",
      "faq.refund.q5": "माझा रिफंड वेगळ्या खात्यात पाठवता येईल का?",
      "faq.refund.a5": "रिफंड सामान्यतः मूळ पेमेंट पद्धतीत दिला जातो. पर्यायी व्यवस्थेसाठी सहाय्याशी संपर्क साधा.",

      "help.title": "मदत हवी आहे?", "help.subtitle": "कोणत्याही परतावा किंवा रिफंड प्रश्नांसाठी आमची ग्राहक सहाय्य टीम उपलब्ध आहे.",
      "help.emailLabel": "ईमेल", "help.phoneLabel": "फोन", "help.hoursLabel": "सहाय्य वेळ",
      "help.hoursValue": "सोम–शनि, सकाळी 9:00 – सायं 7:00",
      "help.rightText": "परतावा, बदली किंवा भाडे रिफंडबद्दल अजूनही प्रश्न आहेत का? संपर्क साधा, आमची टीम लवकरच तुमच्याशी संपर्क साधेल.",
      "help.contactBtn": "संपर्क करा",

      "crosslink.toRefund.eyebrow": "संबंधित",
      "crosslink.toRefund.text": "तुमचे पैसे कसे आणि केव्हा परत केले जातात हे जाणून घ्यायचे आहे का?",
      "crosslink.toRefund.btn": "रिफंड धोरण पहा",
      "crosslink.toReturn.eyebrow": "संबंधित",
      "crosslink.toReturn.text": "आधी परतावा किंवा बदली विनंती सुरू करायची आहे का?",
      "crosslink.toReturn.btn": "परतावा धोरण पहा",

      "footer.disclaimerLabel": "अस्वीकरण:", "footer.disclaimer": "AgriCart कोणत्याही वेळी हे धोरण बदलण्याचा अधिकार राखून ठेवते.",
      "footer.lastUpdatedLabel": "शेवटचे अद्यतनित:", "footer.rights": "सर्व हक्क राखीव.",
      "footer.tagline": "एंटरप्राइझ सस्टेनेबल अ‍ॅग्रीटेक पोर्टल"
    }
  };

  var LANG_LABELS = { en: "English", hi: "हिंदी", mr: "मराठी" };
  var currentLang = "en";

  function applyTranslations(lang) {
    var dict = translations[lang] || translations.en;
    document.querySelectorAll("[data-i18n]").forEach(function (el) {
      var key = el.getAttribute("data-i18n");
      if (dict[key]) { el.textContent = dict[key]; }
    });
    document.documentElement.setAttribute("lang", lang);
    currentLang = lang;

    var trigger = document.getElementById("langTriggerLabel");
    if (trigger) trigger.textContent = LANG_LABELS[lang] || LANG_LABELS.en;
    document.querySelectorAll(".lang-menu button").forEach(function (btn) {
      btn.classList.toggle("active", btn.getAttribute("data-lang") === lang);
    });
  }

  global.AgriCartI18n = {
    setLanguage: function (lang) {
      if (!translations[lang]) return;
      applyTranslations(lang);
    },
    getLanguage: function () { return currentLang; },
    t: function (key) { return (translations[currentLang] && translations[currentLang][key]) || key; }
  };

  document.addEventListener("agricart:languagechange", function (e) {
    if (e && e.detail && e.detail.lang) { global.AgriCartI18n.setLanguage(e.detail.lang); }
  });

  document.addEventListener("DOMContentLoaded", function () {
    applyTranslations("en");
  });

})(window);
