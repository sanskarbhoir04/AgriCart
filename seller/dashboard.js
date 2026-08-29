// =====================================================================
// seller/dashboard.js — AgriCart Seller Dashboard client app.
// Talks only to seller_api.php (always scoped server-side to the
// logged-in seller). Supports English + Marathi via SD_T below.
// =====================================================================

/* ---------------------------------------------------------------- i18n */
const SD_T = {
en: {
    topbarTitle:"Seller Dashboard", topbarSub:"Manage your listings, orders, and earnings on AgriCart.", viewStorefront:"View Storefront",
    navGroupOverview:"Overview", navGroupSelling:"Selling", navGroupRentals:"Rentals", navGroupInsights:"Insights", navGroupAccount:"Account", brandTag:"Seller Hub",
    navDashboard:"Dashboard", navProducts:"My Products", navAdd:"Add Product", navStock:"Stock Management",
    navOrders:"Orders", navSales:"Sales History", navCustomers:"Customers", navReviews:"Reviews",
    navEarnings:"Earnings & Payouts", navInvoices:"Sales Invoices", navNotifications:"Notifications", navLogout:"Logout",
    navEquipment:"My Equipment", navAddEquipment:"Add Equipment", navRentalBookings:"Rental Bookings",
    sidebarFoot:"AgriCart Seller Console",
    equipTitle:"My Equipment", addEquipment:"Add Equipment", editEquipment:"Edit Equipment", equipSearchPh:"Search equipment...",
    equipApprovalNote:"New equipment listings need admin approval before they appear on the Rental Hub.",
    thEquipImage:"Image", thEquipName:"Equipment", thType:"Type", thRentDay:"Rent/Day", thHp:"HP",
    thCondition:"Condition", thAvailable:"Available", thApproval:"Approval", thActiveBookings:"Active Bookings",
    lblEquipName:"Equipment Name", lblEquipType:"Type", lblRentPerDay:"Rent per Day (₹)", lblHp:"HP (Horsepower)",
    lblBrand:"Brand", lblModel:"Model", lblSecurityDeposit:"Security Deposit (₹)",
    lblOperatorAvailable:"Operator Available", lblFuelIncluded:"Fuel Included", lblCity:"City", lblAvailability:"Listing Active",
    typeTractor:"Tractor", typeHarvester:"Harvester", typeRotavator:"Rotavator", typePlough:"Plough",
    typeCultivator:"Cultivator", typeSprayer:"Sprayer", typeThresher:"Thresher", typeOther:"Other",
    condExcellent:"Excellent", condGood:"Good", condAverage:"Average",
    confirmDeleteEquip:"Remove this equipment?", deleteEquipWarning:"This will remove the listing from the Rental Hub. This action cannot be undone.",
    emptyEquipment:"No equipment listed yet", emptyEquipmentSub:"Equipment you list for rent will appear here.",
    rentalBookingsTitle:"Rental Bookings", rentalSearchPh:"Search booking # / equipment / customer",
    rbPending:"Pending", rbConfirmed:"Confirmed", rbOnTheWay:"On the Way", rbCompleted:"Completed", rbCancelled:"Cancelled",
    thBookingId:"Booking #", thEquipment:"Equipment", thCustomer2:"Customer", thRentalDates:"Rental Dates",
    thHours:"Hours", thAmount2:"Amount", thBookingStatus:"Status", thPaymentStatus:"Payment",
    emptyRentalBookings:"No rental bookings yet", emptyRentalBookingsSub:"Bookings for your equipment will show up here.",
    noProdTitle:"You haven't listed any products yet.", noProdSub:"List your first product to start selling on AgriCart.", noProdBtn:"Add Product",
    dashTitle:"Dashboard Overview",
    cardTotalProducts:"Total Products Listed", cardTotalStock:"Total Stock Available", cardOutOfStock:"Out-of-Stock Products",
    cardUnitsSold:"Total Units Sold", cardTotalOrders:"Total Orders", cardGrossSales:"Gross Sales Amount",
    cardPlatformCharges:"Platform Charges Deducted", cardNetRevenue:"Net Revenue Generated", cardAvgRating:"Average Product Rating",
    cardPendingOrders:"Pending Orders",
    analyticsTitle:"Sales & Revenue Analytics", rangeToday:"Today", range7d:"Last 7 Days", range30d:"Last 30 Days",
    rangeMonth:"This Month", rangeCustom:"Custom Date Range", apply:"Apply",
    tileDaily:"Daily Sales", tileWeekly:"Weekly Sales", tileMonthly:"Monthly Sales", tileMonthlyRev:"Monthly Revenue",
    tilePlatformCharges:"Platform Charges", tileNetEarnings:"Net Earnings",
    bestSelling:"Best-Selling Product", mostViewed:"Most Viewed Product", noData:"Not enough data yet",
    prodTitle:"My Products", prodSearchPh:"Search products...",
    thImage:"Image", thName:"Product Name", thCategory:"Category", thOriginalStock:"Original Stock", thSold:"Sold Qty",
    thRemaining:"Remaining Stock", thPrice:"Price", thStatus:"Status", thActions:"Actions",
    stInStock:"In Stock", stLowStock:"Low Stock", stOutOfStock:"Out of Stock",
    stApprovalPending:"Pending Approval", stApprovalApproved:"Approved", stApprovalRejected:"Rejected",
    edit:"Edit", updateStockBtn:"Update Stock", delete:"Delete",
    stockTitle:"Stock Management",
    ordersTitle:"Orders", orderSearchPh:"Search order id / buyer / product", all:"All Statuses",
    stNewOrder:"New Order", stConfirmed:"Confirmed", stPacked:"Packed", stShipped:"Shipped",
    stDelivered:"Delivered", stCancelled:"Cancelled", stReturned:"Returned",
    thOrderId:"Order ID", thBuyer:"Buyer", thProduct:"Product", thQty:"Qty", thTotal:"Total Amount",
    thCharges:"Platform Charges", thNet:"Seller Net", thDate:"Date & Time", thPayment:"Payment", thOrderStatus:"Order Status", thInvoice:"Invoice",
    view:"View",
    perfTitle:"Product Performance", thViews:"Total Views", thConversion:"Conversion Rate", thRevenue:"Revenue", thRating:"Avg Rating",
    bestPerformer:"Best Performer", lowPerformer:"Needs Attention",
    custTitle:"Customer Purchase History", thCustomer:"Customer Name", thProductsBought:"Products Purchased",
    thOrders:"Orders", thLastPurchase:"Last Purchase", thLocation:"Delivery Location", contact:"Contact",
    revTitle:"Ratings & Reviews", allRatings:"All Ratings", avgRating:"Average Rating", totalReviews:"Total Reviews",
    verifiedPurchase:"Verified Purchase", reply:"Reply", yourReply:"Your Reply", writeReply:"Write a reply...", post:"Post Reply",
    invoicesTitle:"Sales Invoices", invoiceSearchPh:"Search invoice no. / order id",
    invPaid:"Paid", invPending:"Pending", invUnpaid:"Unpaid",
    settlePending:"Pending", settleAvailable:"Ready for Payout", settlePaid:"Paid Out",
    earnTitle:"Earnings & Payouts", availableBalance:"Available Balance", pendingBalance:"Pending Balance",
    totalEarnings:"Total Earnings", totalCharges:"Total Platform Charges", totalPaid:"Amount Already Paid",
    processingAmount:"Processing (Requested)",
    nextPayout:"Next Payout Date", payoutDetailsTitle:"Payout Details", payoutHistoryTitle:"Payout History",
    downloadStatement:"Download Statement", bankDetails:"Bank / UPI Details", noBankDetails:"No bank or UPI details on file yet.",
    thAmount:"Amount", thMethod:"Method", thPayoutStatus:"Status", thRequested:"Requested On",
    payoutPending:"Pending", payoutProcessing:"Processing", payoutCompleted:"Completed",
    notifTitle:"Notifications", markAllRead:"Mark all as read",
    businessName:"Business Name", bankAccNo:"Bank Account Number", upiId:"UPI ID",
    editProduct:"Edit Product", lblName:"Product Name", lblPrice:"Price (₹)", lblCategory:"Category", lblBrand:"Brand",
    lblDesc:"Description", lblCondition:"Condition", condNew:"New", condUsed:"Used", lblDelivery:"Delivery Available",
    yes:"Yes", no:"No", cancel:"Cancel", save:"Save",
    updateStock:"Update Stock", currentStock:"Current Stock", stockMode:"Mode", addUnits:"Add Units",
    setExact:"Set Exact Value", quantity:"Quantity",
    confirmDelete:"Delete this product?", deleteWarning:"This will remove the product from your storefront. This action cannot be undone.", delete2:"Delete",
    emptyProducts:"No products yet", emptyProductsSub:"Products you list will appear here.",
    emptyOrders:"No orders yet", emptyOrdersSub:"Orders for your products will show up here.",
    emptyCustomers:"No customers yet", emptyReviews:"No reviews yet", emptyNotifs:"You're all caught up!",
    emptyPayouts:"No payout history yet", loading:"Loading...",
    toastSaved:"Saved successfully", toastDeleted:"Product deleted", toastUpdated:"Order updated", toastError:"Something went wrong",
    invoiceTitle:"Invoice", invoiceOrderId:"Order Item ID", invoiceClose:"Close",
    contactTitle:"Contact Customer", contactPhone:"Phone (masked)", contactClose:"Close",
    navSellingPrefs:"Selling Preferences",
    navInactive:"Inactive Listings", inactiveTitle:"Inactive Listings",
    inactiveNote:"Deactivated products and equipment stay here — nothing is ever permanently deleted while it has order, booking, or review history. Restore any listing to make it active again.",
    inactiveProductsTitle:"Inactive Products", inactiveEquipmentTitle:"Inactive Equipment",
    confirmRestore:"Restore this product?", restoreWarning:"This will make the product active again and it will reappear in your storefront and active listings.", restore:"Restore",
    confirmActivateEquip:"Activate this equipment?", activateEquipWarning:"This will make the equipment bookable again on the Rental Hub.",
    confirmDeactivateEquip:"Deactivate this equipment?", deactivateEquipWarning:"This will hide it from the Rental Hub and stop new bookings. Existing bookings stay in your history, and you can activate it again anytime from Inactive Listings.", deactivate:"Deactivate",
    confirmDeactivate:"Deactivate this product?", deactivateWarning:"This will hide it from your storefront and active listings. Nothing is deleted — you can restore it anytime from Inactive Listings.",
    lblLowStockLimit:"Low-Stock Alert Level",
    toastRestored:"Restored successfully", toastDeactivated:"Deactivated successfully",
    stAvailable:"Available", stBooked:"Booked", stUnavailable:"Unavailable", stDeactivated:"Deactivated",
    stRefunded:"Refunded",
    cardLowStock:"Low-Stock Products", cardReturnedValue:"Returned Order Value", cardRefunds:"Refunds",
    emptyInactiveProducts:"No inactive products", emptyInactiveProductsSub:"Products you deactivate will appear here.",
    emptyInactiveEquipment:"No inactive equipment", emptyInactiveEquipmentSub:"Equipment you deactivate will appear here.",
    ovWelcome:"Welcome back, to your AgriCart store", ovGoodMorning:"Good Morning", ovGoodAfternoon:"Good Afternoon", ovGoodEvening:"Good Evening",
    todoTitle:"To Do List", todoUnpaid:"Unpaid Orders", todoToProcess:"To-Process Shipment", todoShipped:"Shipping Processed", todoSoldOut:"Sold Out Products",
    statViews:"Product Views", statOrders:"Orders", statUnitsSold:"Units Sold", statRating:"Avg Rating",
    topSellingTitle:"Top Selling Product", viewAll:"View All", latestInvoiceTitle:"Latest Invoice",
    sellerRole:"Seller", availableFunds:"Available Funds", withdraw:"Withdraw",
    recentTestimonials:"Recent Testimonials", activeCustomers:"Customers",
    requestWithdrawal:"Request Withdrawal", withdrawAmount:"Amount to Withdraw (₹)", withdrawMethod:"Payout Method",
    methodBank:"Bank Transfer", methodUpi:"UPI", submitWithdraw:"Submit Request",
    minWithdrawNote:"Minimum withdrawal is ₹200. The amount will be held from your available balance until an admin approves it.",
    editPayoutDetails:"Edit Payout Details", lblBusinessName:"Business Name", lblBankAccName:"Account Holder Name",
    lblBankAccNo:"Bank Account Number", lblBankIfsc:"IFSC Code", lblUpiId:"UPI ID", saveDetails:"Save Details",
    payoutRejected:"Rejected", toastWithdrawSubmitted:"Withdrawal request submitted", toastDetailsSaved:"Details saved successfully",
    invoiceSignatureTitle:"Invoice Signature & Stamp", invoiceSignatureHint:"Shown as the Authorized Signatory on invoices your buyers receive.",
    editSignatureStamp:"Edit Signature & Stamp", lblDigitalSignature:"Digital Signature", lblOfficialStamp:"Official Stamp",
    lblAuthSignatoryName:"Authorized Signatory Name", lblDesignation:"Designation", uploaded:"Uploaded", missing:"Missing",
    navAccount:"My Account", accountTitle:"My Account", accountDetailsTitle:"Account Details",
    lblFullName:"Full Name", lblMobile:"Mobile Number", lblEmail:"Email Address", lblAddress:"Address (Village, Taluka, District)",
    changePasswordTitle:"Change Password", lblCurrentPassword:"Current Password", lblNewPassword:"New Password", lblConfirmPassword:"Confirm New Password",
    passwordHint:"Leave these blank if you don't want to change your password.", updatePassword:"Update Password",
    pwdMismatch:"New password and confirm password do not match.", pwdShort:"New password must be at least 6 characters.",
    toastPasswordUpdated:"Password updated successfully",
},

mr: {
    topbarTitle:"विक्रेता डॅशबोर्ड", topbarSub:"तुमची उत्पादने, ऑर्डर्स आणि कमाई येथे व्यवस्थापित करा.", viewStorefront:"स्टोअरफ्रंट पहा",
    navGroupOverview:"आढावा", navGroupSelling:"विक्री", navGroupRentals:"भाडे", navGroupInsights:"विश्लेषण", navGroupAccount:"खाते", brandTag:"विक्रेता हब",
    navDashboard:"डॅशबोर्ड", navProducts:"माझी उत्पादने", navAdd:"उत्पादन जोडा", navStock:"स्टॉक व्यवस्थापन",
    navOrders:"ऑर्डर्स", navSales:"विक्री इतिहास", navCustomers:"ग्राहक", navReviews:"पुनरावलोकने",
    navEarnings:"कमाई आणि पेआउट", navInvoices:"विक्री पावत्या", navNotifications:"सूचना", navLogout:"लॉगआउट",
    navEquipment:"माझी उपकरणे", navAddEquipment:"उपकरण जोडा", navRentalBookings:"भाडे बुकिंग्स",
    sidebarFoot:"AgriCart विक्रेता कन्सोल",
    equipTitle:"माझी उपकरणे", addEquipment:"उपकरण जोडा", editEquipment:"उपकरण संपादित करा", equipSearchPh:"उपकरण शोधा...",
    equipApprovalNote:"नवीन उपकरण यादी Rental Hub वर दिसण्यापूर्वी admin मंजुरी आवश्यक आहे.",
    thEquipImage:"प्रतिमा", thEquipName:"उपकरण", thType:"प्रकार", thRentDay:"भाडे/दिवस", thHp:"HP",
    thCondition:"स्थिती", thAvailable:"उपलब्ध", thApproval:"मंजुरी", thActiveBookings:"सक्रिय बुकिंग्स",
    lblEquipName:"उपकरणाचे नाव", lblEquipType:"प्रकार", lblRentPerDay:"भाडे प्रति दिवस (₹)", lblHp:"HP (अश्वशक्ती)",
    lblBrand:"ब्रँड", lblModel:"मॉडेल", lblSecurityDeposit:"सुरक्षा ठेव (₹)",
    lblOperatorAvailable:"ऑपरेटर उपलब्ध", lblFuelIncluded:"इंधन समाविष्ट", lblCity:"शहर", lblAvailability:"यादी सक्रिय",
    typeTractor:"ट्रॅक्टर", typeHarvester:"हार्वेस्टर", typeRotavator:"रोटाव्हेटर", typePlough:"नांगर",
    typeCultivator:"कल्टिव्हेटर", typeSprayer:"स्प्रेअर", typeThresher:"थ्रेशर", typeOther:"इतर",
    condExcellent:"उत्कृष्ट", condGood:"चांगली", condAverage:"सर्वसाधारण",
    confirmDeleteEquip:"हे उपकरण काढायचे?", deleteEquipWarning:"हे Rental Hub वरून यादी काढून टाकेल. ही क्रिया पूर्ववत केली जाऊ शकत नाही.",
    emptyEquipment:"अद्याप उपकरणे सूचीबद्ध नाहीत", emptyEquipmentSub:"तुम्ही भाड्याने दिलेली उपकरणे येथे दिसतील.",
    rentalBookingsTitle:"भाडे बुकिंग्स", rentalSearchPh:"बुकिंग # / उपकरण / ग्राहक शोधा",
    rbPending:"प्रलंबित", rbConfirmed:"निश्चित", rbOnTheWay:"मार्गावर", rbCompleted:"पूर्ण झाले", rbCancelled:"रद्द केले",
    thBookingId:"बुकिंग #", thEquipment:"उपकरण", thCustomer2:"ग्राहक", thRentalDates:"भाडे तारखा",
    thHours:"तास", thAmount2:"रक्कम", thBookingStatus:"स्थिती", thPaymentStatus:"पेमेंट",
    emptyRentalBookings:"अद्याप भाडे बुकिंग्स नाहीत", emptyRentalBookingsSub:"तुमच्या उपकरणांसाठीच्या बुकिंग्स येथे दिसतील.",
    noProdTitle:"तुम्ही अद्याप कोणतेही उत्पादन सूचीबद्ध केलेले नाही.", noProdSub:"विक्री सुरू करण्यासाठी तुमचे पहिले उत्पादन सूचीबद्ध करा.", noProdBtn:"उत्पादन जोडा",
    dashTitle:"डॅशबोर्ड विहंगावलोकन",
    cardTotalProducts:"एकूण सूचीबद्ध उत्पादने", cardTotalStock:"एकूण उपलब्ध स्टॉक", cardOutOfStock:"स्टॉक संपलेली उत्पादने",
    cardUnitsSold:"एकूण विकलेली एकके", cardTotalOrders:"एकूण ऑर्डर्स", cardGrossSales:"एकूण विक्री रक्कम",
    cardPlatformCharges:"वजा केलेले प्लॅटफॉर्म शुल्क", cardNetRevenue:"निव्वळ महसूल", cardAvgRating:"सरासरी उत्पादन रेटिंग",
    cardPendingOrders:"प्रलंबित ऑर्डर्स",
    analyticsTitle:"विक्री आणि महसूल विश्लेषण", rangeToday:"आज", range7d:"मागील ७ दिवस", range30d:"मागील ३० दिवस",
    rangeMonth:"या महिन्यात", rangeCustom:"सानुकूल तारीख श्रेणी", apply:"लागू करा",
    tileDaily:"दैनिक विक्री", tileWeekly:"साप्ताहिक विक्री", tileMonthly:"मासिक विक्री", tileMonthlyRev:"मासिक महसूल",
    tilePlatformCharges:"प्लॅटफॉर्म शुल्क", tileNetEarnings:"निव्वळ कमाई",
    bestSelling:"सर्वाधिक विकले जाणारे उत्पादन", mostViewed:"सर्वाधिक पाहिले गेलेले उत्पादन", noData:"अद्याप पुरेसा डेटा नाही",
    prodTitle:"माझी उत्पादने", prodSearchPh:"उत्पादने शोधा...",
    thImage:"प्रतिमा", thName:"उत्पादनाचे नाव", thCategory:"श्रेणी", thOriginalStock:"मूळ स्टॉक", thSold:"विकलेले प्रमाण",
    thRemaining:"उर्वरित स्टॉक", thPrice:"किंमत", thStatus:"स्थिती", thActions:"क्रिया",
    stInStock:"स्टॉकमध्ये", stLowStock:"कमी स्टॉक", stOutOfStock:"स्टॉक संपला",
    stApprovalPending:"मंजुरी प्रलंबित", stApprovalApproved:"मंजूर", stApprovalRejected:"नाकारले",
    edit:"संपादित करा", updateStockBtn:"स्टॉक अपडेट करा", delete:"हटवा",
    stockTitle:"स्टॉक व्यवस्थापन",
    ordersTitle:"ऑर्डर्स", orderSearchPh:"ऑर्डर आयडी / ग्राहक / उत्पादन शोधा", all:"सर्व स्थिती",
    stNewOrder:"नवीन ऑर्डर", stConfirmed:"निश्चित", stPacked:"पॅक केले", stShipped:"पाठवले",
    stDelivered:"वितरित", stCancelled:"रद्द केले", stReturned:"परत केले",
    thOrderId:"ऑर्डर आयडी", thBuyer:"ग्राहक", thProduct:"उत्पादन", thQty:"प्रमाण", thTotal:"एकूण रक्कम",
    thCharges:"प्लॅटफॉर्म शुल्क", thNet:"विक्रेता निव्वळ", thDate:"दिनांक आणि वेळ", thPayment:"पेमेंट", thOrderStatus:"ऑर्डर स्थिती", thInvoice:"पावती",
    view:"पहा",
    perfTitle:"उत्पादन कामगिरी", thViews:"एकूण दृश्ये", thConversion:"रूपांतरण दर", thRevenue:"महसूल", thRating:"सरासरी रेटिंग",
    bestPerformer:"सर्वोत्तम कामगिरी", lowPerformer:"लक्ष देणे आवश्यक",
    custTitle:"ग्राहक खरेदी इतिहास", thCustomer:"ग्राहकाचे नाव", thProductsBought:"खरेदी केलेली उत्पादने",
    thOrders:"ऑर्डर्स", thLastPurchase:"शेवटची खरेदी", thLocation:"डिलिव्हरी ठिकाण", contact:"संपर्क",
    revTitle:"रेटिंग आणि पुनरावलोकने", allRatings:"सर्व रेटिंग्स", avgRating:"सरासरी रेटिंग", totalReviews:"एकूण पुनरावलोकने",
    verifiedPurchase:"सत्यापित खरेदी", reply:"उत्तर द्या", yourReply:"तुमचे उत्तर", writeReply:"उत्तर लिहा...", post:"उत्तर पोस्ट करा",
    invoicesTitle:"विक्री पावत्या", invoiceSearchPh:"पावती क्र. / ऑर्डर आयडी शोधा",
    invPaid:"दिले", invPending:"प्रलंबित", invUnpaid:"न भरलेले",
    settlePending:"प्रलंबित", settleAvailable:"पेआउटसाठी तयार", settlePaid:"पैसे दिले",
    earnTitle:"कमाई आणि पेआउट", availableBalance:"उपलब्ध शिल्लक", pendingBalance:"प्रलंबित शिल्लक",
    totalEarnings:"एकूण कमाई", totalCharges:"एकूण प्लॅटफॉर्म शुल्क", totalPaid:"आधीच दिलेली रक्कम",
    processingAmount:"प्रक्रियेत (विनंती केलेली)",
    nextPayout:"पुढील पेआउट तारीख", payoutDetailsTitle:"पेआउट तपशील", payoutHistoryTitle:"पेआउट इतिहास",
    downloadStatement:"स्टेटमेंट डाउनलोड करा", bankDetails:"बँक / UPI तपशील", noBankDetails:"अद्याप कोणतेही बँक किंवा UPI तपशील नाहीत.",
    thAmount:"रक्कम", thMethod:"पद्धत", thPayoutStatus:"स्थिती", thRequested:"विनंती केली",
    payoutPending:"प्रलंबित", payoutProcessing:"प्रक्रियेत", payoutCompleted:"पूर्ण झाले",
    notifTitle:"सूचना", markAllRead:"सर्व वाचले म्हणून चिन्हांकित करा",
    businessName:"व्यवसायाचे नाव", bankAccNo:"बँक खाते क्रमांक", upiId:"UPI आयडी",
    editProduct:"उत्पादन संपादित करा", lblName:"उत्पादनाचे नाव", lblPrice:"किंमत (₹)", lblCategory:"श्रेणी", lblBrand:"ब्रँड",
    lblDesc:"वर्णन", lblCondition:"स्थिती", condNew:"नवीन", condUsed:"वापरलेले", lblDelivery:"डिलिव्हरी उपलब्ध",
    yes:"होय", no:"नाही", cancel:"रद्द करा", save:"जतन करा",
    updateStock:"स्टॉक अपडेट करा", currentStock:"सध्याचा स्टॉक", stockMode:"पद्धत", addUnits:"युनिट्स जोडा",
    setExact:"निश्चित मूल्य सेट करा", quantity:"प्रमाण",
    confirmDelete:"हे उत्पादन हटवायचे?", deleteWarning:"हे तुमच्या स्टोअरफ्रंटवरून उत्पादन काढून टाकेल. ही क्रिया पूर्ववत केली जाऊ शकत नाही.", delete2:"हटवा",
    emptyProducts:"अद्याप उत्पादने नाहीत", emptyProductsSub:"तुम्ही सूचीबद्ध केलेली उत्पादने येथे दिसतील.",
    emptyOrders:"अद्याप ऑर्डर्स नाहीत", emptyOrdersSub:"तुमच्या उत्पादनांसाठीच्या ऑर्डर्स येथे दिसतील.",
    emptyCustomers:"अद्याप ग्राहक नाहीत", emptyReviews:"अद्याप पुनरावलोकने नाहीत", emptyNotifs:"तुम्ही अद्ययावत आहात!",
    emptyPayouts:"अद्याप पेआउट इतिहास नाही", loading:"लोड होत आहे...",
    toastSaved:"यशस्वीरित्या जतन केले", toastDeleted:"उत्पादन हटवले", toastUpdated:"ऑर्डर अपडेट केली", toastError:"काहीतरी चूक झाली",
    invoiceTitle:"पावती", invoiceOrderId:"ऑर्डर आयटम आयडी", invoiceClose:"बंद करा",
    contactTitle:"ग्राहकाशी संपर्क", contactPhone:"फोन (मास्क केलेला)", contactClose:"बंद करा",
    navSellingPrefs:"विक्री प्राधान्ये",
    navInactive:"निष्क्रिय यादी", inactiveTitle:"निष्क्रिय यादी",
    inactiveNote:"निष्क्रिय केलेली उत्पादने आणि उपकरणे येथे राहतात — ऑर्डर, बुकिंग किंवा पुनरावलोकन इतिहास असेपर्यंत ते कधीही कायमचे हटवले जात नाहीत. पुन्हा सक्रिय करण्यासाठी कोणतीही यादी पुनर्संचयित करा.",
    inactiveProductsTitle:"निष्क्रिय उत्पादने", inactiveEquipmentTitle:"निष्क्रिय उपकरणे",
    confirmRestore:"हे उत्पादन पुनर्संचयित करायचे?", restoreWarning:"हे उत्पादन पुन्हा सक्रिय करेल आणि ते तुमच्या स्टोअरफ्रंट आणि सक्रिय यादीत पुन्हा दिसेल.", restore:"पुनर्संचयित करा",
    confirmActivateEquip:"हे उपकरण सक्रिय करायचे?", activateEquipWarning:"हे उपकरण Rental Hub वर पुन्हा बुक करण्यायोग्य होईल.",
    confirmDeactivateEquip:"हे उपकरण निष्क्रिय करायचे?", deactivateEquipWarning:"हे Rental Hub वरून लपवेल आणि नवीन बुकिंग थांबवेल. जुन्या बुकिंग्स तुमच्या इतिहासात राहतील, आणि तुम्ही निष्क्रिय यादीतून ते केव्हाही पुन्हा सक्रिय करू शकता.", deactivate:"निष्क्रिय करा",
    confirmDeactivate:"हे उत्पादन निष्क्रिय करायचे?", deactivateWarning:"हे तुमच्या स्टोअरफ्रंट आणि सक्रिय यादीतून लपवेल. काहीही हटवले जात नाही — तुम्ही निष्क्रिय यादीतून ते केव्हाही पुनर्संचयित करू शकता.",
    lblLowStockLimit:"कमी-स्टॉक सूचना पातळी",
    toastRestored:"यशस्वीरित्या पुनर्संचयित केले", toastDeactivated:"यशस्वीरित्या निष्क्रिय केले",
    stAvailable:"उपलब्ध", stBooked:"बुक केलेले", stUnavailable:"अनुपलब्ध", stDeactivated:"निष्क्रिय",
    stRefunded:"परतावा दिला",
    cardLowStock:"कमी-स्टॉक उत्पादने", cardReturnedValue:"परत केलेल्या ऑर्डरचे मूल्य", cardRefunds:"परतावे",
    emptyInactiveProducts:"निष्क्रिय उत्पादने नाहीत", emptyInactiveProductsSub:"तुम्ही निष्क्रिय केलेली उत्पादने येथे दिसतील.",
    emptyInactiveEquipment:"निष्क्रिय उपकरणे नाहीत", emptyInactiveEquipmentSub:"तुम्ही निष्क्रिय केलेली उपकरणे येथे दिसतील.",
    ovWelcome:"पुन्हा स्वागत आहे, तुमच्या AgriCart स्टोअरमध्ये", ovGoodMorning:"सुप्रभात", ovGoodAfternoon:"शुभ दुपार", ovGoodEvening:"शुभ संध्याकाळ",
    todoTitle:"करायच्या गोष्टी", todoUnpaid:"न भरलेल्या ऑर्डर्स", todoToProcess:"प्रक्रिया करायच्या शिपमेंट", todoShipped:"पाठवलेल्या शिपमेंट", todoSoldOut:"स्टॉक संपलेली उत्पादने",
    statViews:"उत्पादन दृश्ये", statOrders:"ऑर्डर्स", statUnitsSold:"विकलेले युनिट्स", statRating:"सरासरी रेटिंग",
    topSellingTitle:"सर्वाधिक विकले जाणारे उत्पादन", viewAll:"सर्व पहा", latestInvoiceTitle:"नवीनतम पावती",
    sellerRole:"विक्रेता", availableFunds:"उपलब्ध निधी", withdraw:"पैसे काढा",
    recentTestimonials:"अलीकडील अभिप्राय", activeCustomers:"ग्राहक",
    requestWithdrawal:"पैसे काढण्याची विनंती", withdrawAmount:"काढायची रक्कम (₹)", withdrawMethod:"पेआउट पद्धत",
    methodBank:"बँक हस्तांतरण", methodUpi:"UPI", submitWithdraw:"विनंती पाठवा",
    minWithdrawNote:"किमान रक्कम ₹२०० आहे. Admin मंजुरी देईपर्यंत ही रक्कम तुमच्या उपलब्ध शिल्लकीतून रोखली जाईल.",
    editPayoutDetails:"पेआउट तपशील संपादित करा", lblBusinessName:"व्यवसायाचे नाव", lblBankAccName:"खातेधारकाचे नाव",
    lblBankAccNo:"बँक खाते क्रमांक", lblBankIfsc:"IFSC कोड", lblUpiId:"UPI आयडी", saveDetails:"तपशील जतन करा",
    payoutRejected:"नाकारले", toastWithdrawSubmitted:"पैसे काढण्याची विनंती पाठवली", toastDetailsSaved:"तपशील यशस्वीरित्या जतन केले",
    navAccount:"माझे खाते", accountTitle:"माझे खाते", accountDetailsTitle:"खाते तपशील",
    lblFullName:"पूर्ण नाव", lblMobile:"मोबाईल क्रमांक", lblEmail:"ईमेल पत्ता", lblAddress:"पत्ता (गाव, तालुका, जिल्हा)",
    changePasswordTitle:"पासवर्ड बदला", lblCurrentPassword:"सध्याचा पासवर्ड", lblNewPassword:"नवीन पासवर्ड", lblConfirmPassword:"नवीन पासवर्डची खात्री करा",
    passwordHint:"पासवर्ड बदलायचा नसेल तर हे रिकामे ठेवा.", updatePassword:"पासवर्ड अपडेट करा",
    pwdMismatch:"नवीन पासवर्ड आणि खात्री पासवर्ड जुळत नाहीत.", pwdShort:"नवीन पासवर्ड किमान ६ अक्षरांचा असावा.",
    toastPasswordUpdated:"पासवर्ड यशस्वीरित्या अपडेट केला",
},

hi: {
    topbarTitle:"विक्रेता डैशबोर्ड", topbarSub:"अपने लिस्टिंग, ऑर्डर और कमाई यहाँ प्रबंधित करें.", viewStorefront:"स्टोरफ्रंट देखें",
    navGroupOverview:"अवलोकन", navGroupSelling:"बिक्री", navGroupRentals:"किराया", navGroupInsights:"विश्लेषण", navGroupAccount:"खाता", brandTag:"विक्रेता हब",
    navDashboard:"डैशबोर्ड", navProducts:"मेरे उत्पाद", navAdd:"उत्पाद जोड़ें", navStock:"स्टॉक प्रबंधन",
    navOrders:"ऑर्डर", navSales:"बिक्री इतिहास", navCustomers:"ग्राहक", navReviews:"समीक्षाएं",
    navEarnings:"कमाई और भुगतान", navInvoices:"बिक्री चालान", navNotifications:"सूचनाएं", navLogout:"लॉगआउट",
    navEquipment:"मेरे उपकरण", navAddEquipment:"उपकरण जोड़ें", navRentalBookings:"किराया बुकिंग",
    sidebarFoot:"AgriCart विक्रेता कंसोल",
    equipTitle:"मेरे उपकरण", addEquipment:"उपकरण जोड़ें", editEquipment:"उपकरण संपादित करें", equipSearchPh:"उपकरण खोजें...",
    equipApprovalNote:"नई उपकरण लिस्टिंग को Rental Hub पर दिखने से पहले एडमिन अनुमोदन की आवश्यकता होती है.",
    thEquipImage:"छवि", thEquipName:"उपकरण", thType:"प्रकार", thRentDay:"किराया/दिन", thHp:"HP",
    thCondition:"स्थिति", thAvailable:"उपलब्ध", thApproval:"अनुमोदन", thActiveBookings:"सक्रिय बुकिंग",
    lblEquipName:"उपकरण का नाम", lblEquipType:"प्रकार", lblRentPerDay:"प्रति दिन किराया (₹)", lblHp:"HP (अश्वशक्ति)",
    lblBrand:"ब्रांड", lblModel:"मॉडल", lblSecurityDeposit:"सुरक्षा जमा (₹)",
    lblOperatorAvailable:"ऑपरेटर उपलब्ध", lblFuelIncluded:"ईंधन शामिल", lblCity:"शहर", lblAvailability:"लिस्टिंग सक्रिय",
    typeTractor:"ट्रैक्टर", typeHarvester:"हार्वेस्टर", typeRotavator:"रोटावेटर", typePlough:"हल",
    typeCultivator:"कल्टीवेटर", typeSprayer:"स्प्रेयर", typeThresher:"थ्रेशर", typeOther:"अन्य",
    condExcellent:"उत्कृष्ट", condGood:"अच्छी", condAverage:"सामान्य",
    confirmDeleteEquip:"यह उपकरण हटाएं?", deleteEquipWarning:"यह Rental Hub से लिस्टिंग हटा देगा. यह क्रिया पूर्ववत नहीं की जा सकती.",
    emptyEquipment:"अभी तक कोई उपकरण सूचीबद्ध नहीं", emptyEquipmentSub:"आपके द्वारा किराए पर दिए गए उपकरण यहाँ दिखाई देंगे.",
    rentalBookingsTitle:"किराया बुकिंग", rentalSearchPh:"बुकिंग # / उपकरण / ग्राहक खोजें",
    rbPending:"लंबित", rbConfirmed:"पुष्टि", rbOnTheWay:"रास्ते में", rbCompleted:"पूर्ण", rbCancelled:"रद्द",
    thBookingId:"बुकिंग #", thEquipment:"उपकरण", thCustomer2:"ग्राहक", thRentalDates:"किराया तिथियां",
    thHours:"घंटे", thAmount2:"राशि", thBookingStatus:"स्थिति", thPaymentStatus:"भुगतान",
    emptyRentalBookings:"अभी तक कोई किराया बुकिंग नहीं", emptyRentalBookingsSub:"आपके उपकरणों की बुकिंग यहाँ दिखाई देंगी.",
    noProdTitle:"आपने अभी तक कोई उत्पाद सूचीबद्ध नहीं किया है.", noProdSub:"AgriCart पर बिक्री शुरू करने के लिए अपना पहला उत्पाद सूचीबद्ध करें.", noProdBtn:"उत्पाद जोड़ें",
    dashTitle:"डैशबोर्ड अवलोकन",
    cardTotalProducts:"कुल सूचीबद्ध उत्पाद", cardTotalStock:"कुल उपलब्ध स्टॉक", cardOutOfStock:"स्टॉक समाप्त उत्पाद",
    cardUnitsSold:"कुल बेची गई इकाइयां", cardTotalOrders:"कुल ऑर्डर", cardGrossSales:"सकल बिक्री राशि",
    cardPlatformCharges:"काटा गया प्लेटफ़ॉर्म शुल्क", cardNetRevenue:"शुद्ध राजस्व", cardAvgRating:"औसत उत्पाद रेटिंग",
    cardPendingOrders:"लंबित ऑर्डर",
    analyticsTitle:"बिक्री और राजस्व विश्लेषण", rangeToday:"आज", range7d:"पिछले 7 दिन", range30d:"पिछले 30 दिन",
    rangeMonth:"इस महीने", rangeCustom:"कस्टम तिथि सीमा", apply:"लागू करें",
    tileDaily:"दैनिक बिक्री", tileWeekly:"साप्ताहिक बिक्री", tileMonthly:"मासिक बिक्री", tileMonthlyRev:"मासिक राजस्व",
    tilePlatformCharges:"प्लेटफ़ॉर्म शुल्क", tileNetEarnings:"शुद्ध कमाई",
    bestSelling:"सबसे ज्यादा बिकने वाला उत्पाद", mostViewed:"सबसे ज्यादा देखा गया उत्पाद", noData:"अभी पर्याप्त डेटा नहीं है",
    prodTitle:"मेरे उत्पाद", prodSearchPh:"उत्पाद खोजें...",
    thImage:"छवि", thName:"उत्पाद का नाम", thCategory:"श्रेणी", thOriginalStock:"मूल स्टॉक", thSold:"बेची गई मात्रा",
    thRemaining:"शेष स्टॉक", thPrice:"मूल्य", thStatus:"स्थिति", thActions:"क्रियाएं",
    stInStock:"स्टॉक में", stLowStock:"कम स्टॉक", stOutOfStock:"स्टॉक समाप्त",
    stApprovalPending:"अनुमोदन लंबित", stApprovalApproved:"स्वीकृत", stApprovalRejected:"अस्वीकृत",
    edit:"संपादित करें", updateStockBtn:"स्टॉक अपडेट करें", delete:"हटाएं",
    stockTitle:"स्टॉक प्रबंधन",
    ordersTitle:"ऑर्डर", orderSearchPh:"ऑर्डर आईडी / खरीदार / उत्पाद खोजें", all:"सभी स्थितियां",
    stNewOrder:"नया ऑर्डर", stConfirmed:"पुष्टि", stPacked:"पैक किया गया", stShipped:"भेजा गया",
    stDelivered:"डिलीवर किया गया", stCancelled:"रद्द", stReturned:"वापस किया गया",
    thOrderId:"ऑर्डर आईडी", thBuyer:"खरीदार", thProduct:"उत्पाद", thQty:"मात्रा", thTotal:"कुल राशि",
    thCharges:"प्लेटफ़ॉर्म शुल्क", thNet:"विक्रेता शुद्ध", thDate:"तारीख और समय", thPayment:"भुगतान", thOrderStatus:"ऑर्डर स्थिति", thInvoice:"रसीद",
    view:"देखें",
    perfTitle:"उत्पाद प्रदर्शन", thViews:"कुल दृश्य", thConversion:"रूपांतरण दर", thRevenue:"राजस्व", thRating:"औसत रेटिंग",
    bestPerformer:"सर्वश्रेष्ठ प्रदर्शन", lowPerformer:"ध्यान देने की आवश्यकता",
    custTitle:"ग्राहक खरीद इतिहास", thCustomer:"ग्राहक का नाम", thProductsBought:"खरीदे गए उत्पाद",
    thOrders:"ऑर्डर", thLastPurchase:"अंतिम खरीद", thLocation:"डिलीवरी स्थान", contact:"संपर्क",
    revTitle:"रेटिंग और समीक्षाएं", allRatings:"सभी रेटिंग", avgRating:"औसत रेटिंग", totalReviews:"कुल समीक्षाएं",
    verifiedPurchase:"सत्यापित खरीद", reply:"उत्तर दें", yourReply:"आपका उत्तर", writeReply:"उत्तर लिखें...", post:"उत्तर पोस्ट करें",
    invoicesTitle:"बिक्री चालान", invoiceSearchPh:"चालान नं. / ऑर्डर आईडी खोजें",
    invPaid:"भुगतान किया", invPending:"लंबित", invUnpaid:"अवैतनिक",
    settlePending:"लंबित", settleAvailable:"भुगतान हेतु तैयार", settlePaid:"भुगतान किया गया",
    earnTitle:"कमाई और भुगतान", availableBalance:"उपलब्ध शेष", pendingBalance:"लंबित शेष",
    totalEarnings:"कुल कमाई", totalCharges:"कुल प्लेटफ़ॉर्म शुल्क", totalPaid:"पहले से भुगतान की गई राशि",
    processingAmount:"प्रक्रियाधीन (अनुरोधित)",
    nextPayout:"अगली भुगतान तिथि", payoutDetailsTitle:"भुगतान विवरण", payoutHistoryTitle:"भुगतान इतिहास",
    downloadStatement:"स्टेटमेंट डाउनलोड करें", bankDetails:"बैंक / UPI विवरण", noBankDetails:"अभी तक कोई बैंक या UPI विवरण दर्ज नहीं है.",
    thAmount:"राशि", thMethod:"तरीका", thPayoutStatus:"स्थिति", thRequested:"अनुरोध किया गया",
    payoutPending:"लंबित", payoutProcessing:"प्रक्रिया में", payoutCompleted:"पूर्ण",
    notifTitle:"सूचनाएं", markAllRead:"सभी को पढ़ा हुआ चिह्नित करें",
    businessName:"व्यवसाय का नाम", bankAccNo:"बैंक खाता संख्या", upiId:"UPI आईडी",
    editProduct:"उत्पाद संपादित करें", lblName:"उत्पाद का नाम", lblPrice:"मूल्य (₹)", lblCategory:"श्रेणी", lblBrand:"ब्रांड",
    lblDesc:"विवरण", lblCondition:"स्थिति", condNew:"नया", condUsed:"उपयोग किया हुआ", lblDelivery:"डिलीवरी उपलब्ध",
    yes:"हां", no:"नहीं", cancel:"रद्द करें", save:"सहेजें",
    updateStock:"स्टॉक अपडेट करें", currentStock:"वर्तमान स्टॉक", stockMode:"मोड", addUnits:"इकाइयां जोड़ें",
    setExact:"सटीक मूल्य सेट करें", quantity:"मात्रा",
    confirmDelete:"यह उत्पाद हटाएं?", deleteWarning:"यह उत्पाद आपके स्टोरफ्रंट से हटा देगा. यह क्रिया पूर्ववत नहीं की जा सकती.", delete2:"हटाएं",
    emptyProducts:"अभी तक कोई उत्पाद नहीं", emptyProductsSub:"आपके द्वारा सूचीबद्ध उत्पाद यहाँ दिखाई देंगे.",
    emptyOrders:"अभी तक कोई ऑर्डर नहीं", emptyOrdersSub:"आपके उत्पादों के ऑर्डर यहाँ दिखाई देंगे.",
    emptyCustomers:"अभी तक कोई ग्राहक नहीं", emptyReviews:"अभी तक कोई समीक्षा नहीं", emptyNotifs:"आप पूरी तरह अपडेट हैं!",
    emptyPayouts:"अभी तक कोई भुगतान इतिहास नहीं", loading:"लोड हो रहा है...",
    toastSaved:"सफलतापूर्वक सहेजा गया", toastDeleted:"उत्पाद हटाया गया", toastUpdated:"ऑर्डर अपडेट किया गया", toastError:"कुछ गलत हो गया",
    invoiceTitle:"रसीद", invoiceOrderId:"ऑर्डर आइटम आईडी", invoiceClose:"बंद करें",
    contactTitle:"ग्राहक से संपर्क करें", contactPhone:"फोन (मास्क्ड)", contactClose:"बंद करें",
    navSellingPrefs:"बिक्री प्राथमिकताएं",
    navInactive:"निष्क्रिय लिस्टिंग", inactiveTitle:"निष्क्रिय लिस्टिंग",
    inactiveNote:"निष्क्रिय किए गए उत्पाद और उपकरण यहाँ रहते हैं — जब तक ऑर्डर, बुकिंग या समीक्षा इतिहास है, वे कभी स्थायी रूप से हटाए नहीं जाते. फिर से सक्रिय करने के लिए किसी भी लिस्टिंग को पुनर्स्थापित करें.",
    inactiveProductsTitle:"निष्क्रिय उत्पाद", inactiveEquipmentTitle:"निष्क्रिय उपकरण",
    confirmRestore:"यह उत्पाद पुनर्स्थापित करें?", restoreWarning:"यह उत्पाद फिर से सक्रिय हो जाएगा और आपके स्टोरफ्रंट व सक्रिय लिस्टिंग में फिर से दिखाई देगा.", restore:"पुनर्स्थापित करें",
    confirmActivateEquip:"यह उपकरण सक्रिय करें?", activateEquipWarning:"यह उपकरण Rental Hub पर फिर से बुक करने योग्य हो जाएगा.",
    confirmDeactivateEquip:"यह उपकरण निष्क्रिय करें?", deactivateEquipWarning:"यह इसे Rental Hub से छिपा देगा और नई बुकिंग रोक देगा. मौजूदा बुकिंग आपके इतिहास में रहेंगी, और आप इसे कभी भी निष्क्रिय लिस्टिंग से फिर से सक्रिय कर सकते हैं.", deactivate:"निष्क्रिय करें",
    confirmDeactivate:"यह उत्पाद निष्क्रिय करें?", deactivateWarning:"यह इसे आपके स्टोरफ्रंट और सक्रिय लिस्टिंग से छिपा देगा. कुछ भी हटाया नहीं जाता — आप इसे कभी भी निष्क्रिय लिस्टिंग से पुनर्स्थापित कर सकते हैं.",
    lblLowStockLimit:"कम-स्टॉक अलर्ट स्तर",
    toastRestored:"सफलतापूर्वक पुनर्स्थापित किया गया", toastDeactivated:"सफलतापूर्वक निष्क्रिय किया गया",
    stAvailable:"उपलब्ध", stBooked:"बुक किया गया", stUnavailable:"अनुपलब्ध", stDeactivated:"निष्क्रिय",
    stRefunded:"वापस भुगतान किया गया",
    cardLowStock:"कम-स्टॉक उत्पाद", cardReturnedValue:"वापस किए गए ऑर्डर का मूल्य", cardRefunds:"रिफंड",
    emptyInactiveProducts:"कोई निष्क्रिय उत्पाद नहीं", emptyInactiveProductsSub:"आपके द्वारा निष्क्रिय किए गए उत्पाद यहाँ दिखाई देंगे.",
    emptyInactiveEquipment:"कोई निष्क्रिय उपकरण नहीं", emptyInactiveEquipmentSub:"आपके द्वारा निष्क्रिय किए गए उपकरण यहाँ दिखाई देंगे.",
    ovWelcome:"आपका फिर से स्वागत है, आपके AgriCart स्टोर में", ovGoodMorning:"सुप्रभात", ovGoodAfternoon:"शुभ दोपहर", ovGoodEvening:"शुभ संध्या",
    todoTitle:"करने योग्य कार्य", todoUnpaid:"अवैतनिक ऑर्डर", todoToProcess:"प्रक्रिया हेतु शिपमेंट", todoShipped:"भेजी गई शिपमेंट", todoSoldOut:"स्टॉक समाप्त उत्पाद",
    statViews:"उत्पाद दृश्य", statOrders:"ऑर्डर", statUnitsSold:"बेचे गए यूनिट", statRating:"औसत रेटिंग",
    topSellingTitle:"सबसे ज़्यादा बिकने वाला उत्पाद", viewAll:"सभी देखें", latestInvoiceTitle:"नवीनतम रसीद",
    sellerRole:"विक्रेता", availableFunds:"उपलब्ध धनराशि", withdraw:"निकालें",
    recentTestimonials:"हालिया प्रतिक्रिया", activeCustomers:"ग्राहक",
    requestWithdrawal:"निकासी का अनुरोध करें", withdrawAmount:"निकालने की राशि (₹)", withdrawMethod:"भुगतान तरीका",
    methodBank:"बैंक ट्रांसफर", methodUpi:"UPI", submitWithdraw:"अनुरोध भेजें",
    minWithdrawNote:"न्यूनतम निकासी राशि ₹200 है. एडमिन द्वारा स्वीकृति मिलने तक यह राशि आपके उपलब्ध शेष से रोक दी जाएगी.",
    editPayoutDetails:"भुगतान विवरण संपादित करें", lblBusinessName:"व्यवसाय का नाम", lblBankAccName:"खाताधारक का नाम",
    lblBankAccNo:"बैंक खाता संख्या", lblBankIfsc:"IFSC कोड", lblUpiId:"UPI आईडी", saveDetails:"विवरण सहेजें",
    payoutRejected:"अस्वीकृत", toastWithdrawSubmitted:"निकासी अनुरोध भेजा गया", toastDetailsSaved:"विवरण सफलतापूर्वक सहेजे गए",
    navAccount:"मेरा खाता", accountTitle:"मेरा खाता", accountDetailsTitle:"खाता विवरण",
    lblFullName:"पूरा नाम", lblMobile:"मोबाइल नंबर", lblEmail:"ईमेल पता", lblAddress:"पता (गांव, तालुका, जिला)",
    changePasswordTitle:"पासवर्ड बदलें", lblCurrentPassword:"वर्तमान पासवर्ड", lblNewPassword:"नया पासवर्ड", lblConfirmPassword:"नए पासवर्ड की पुष्टि करें",
    passwordHint:"पासवर्ड नहीं बदलना है तो इसे खाली छोड़ दें.", updatePassword:"पासवर्ड अपडेट करें",
    pwdMismatch:"नया पासवर्ड और पुष्टि पासवर्ड मेल नहीं खाते.", pwdShort:"नया पासवर्ड कम से कम 6 अक्षरों का होना चाहिए.",
    toastPasswordUpdated:"पासवर्ड सफलतापूर्वक अपडेट किया गया",
},
};
let sdLang = ['mr', 'hi'].includes(localStorage.getItem('agri_lang')) ? localStorage.getItem('agri_lang') : 'en';
function sdT(key) { return (SD_T[sdLang] && SD_T[sdLang][key]) || SD_T.en[key] || key; }
function sdApplyStaticText() {
    document.querySelectorAll('[data-sd]').forEach(el => { el.textContent = sdT(el.getAttribute('data-sd')); });
    document.querySelectorAll('[data-sd-ph]').forEach(el => { el.placeholder = sdT(el.getAttribute('data-sd-ph')); });
}
function sdSetLang(lang) {
    sdLang = lang;
    localStorage.setItem('agri_lang', lang);
    sdApplyStaticText();
    sdSetGreeting();
    sdRenderAllCached();
}
// The dashboard no longer has its own EN/मराठी toggle — language switching
// now comes from the shared site header's language selector. header.php
// calls window.pageLanguageCallback(lang) every time someone changes the
// language there, so hook into that (same pattern used across the rest of
// the site, e.g. pages/sell_product.php). The dashboard now has full
// English + Hindi + Marathi copy (SD_T above).
window.pageLanguageCallback = function (lang) {
    sdSetLang(['mr', 'hi'].includes(lang) ? lang : 'en');
};
// Generic localized-name picker: works for any row that carries
// name / name_mr / name_hi (products, equipment, etc).
function sdLocalName(obj, baseKey) {
    baseKey = baseKey || 'name';
    if (!obj) return '';
    if (sdLang === 'mr' && obj[baseKey + '_mr']) return obj[baseKey + '_mr'];
    if (sdLang === 'hi' && obj[baseKey + '_hi']) return obj[baseKey + '_hi'];
    return obj[baseKey] || '';
}
function sdProdName(p) { return sdLocalName(p, 'name'); }

/* ---------------------------------------------------------------- helpers */
function sdMoney(v) { return '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function sdEsc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
function sdToast(msg) {
    const t = document.getElementById('sdToast');
    document.getElementById('sdToastMsg').textContent = msg;
    t.classList.add('show');
    clearTimeout(window._sdToastTimer);
    window._sdToastTimer = setTimeout(() => t.classList.remove('show'), 2800);
}
let sdDebounceTimer;
function sdDebounce(fn) { clearTimeout(sdDebounceTimer); sdDebounceTimer = setTimeout(fn, 350); }
function sdCloseModal(id) { document.getElementById(id).classList.remove('open'); }
function sdOpenModal(id) { document.getElementById(id).classList.add('open'); }

async function sdApi(action, params = {}, method = 'GET') {
    let url = 'seller_api.php?action=' + encodeURIComponent(action);
    let opts = { method };
    if (method === 'GET') {
        const qs = new URLSearchParams(params).toString();
        if (qs) url += '&' + qs;
    } else {
        const form = new FormData();
        form.append('csrf_token', SD_CSRF);
        Object.keys(params).forEach(k => form.append(k, params[k]));
        opts.body = form;
    }
    try {
        const res = await fetch(url, opts);
        return await res.json();
    } catch (e) {
        return { success: false, error: 'network' };
    }
}

/* ---------------------------------------------------------------- nav / routing */
const sdCache = {};
function sdShowSection(name) {
    document.querySelectorAll('.sd-section').forEach(s => s.classList.remove('active'));
    const sec = document.getElementById('sec-' + name);
    if (sec) sec.classList.add('active');
    document.querySelectorAll('.sd-nav-item[data-section]').forEach(n => n.classList.toggle('active', n.dataset.section === name));
    document.getElementById('sdSidebar').classList.remove('open');
    const scrimEl = document.getElementById('sdSidebarScrim');
    if (scrimEl) scrimEl.classList.remove('show');
    location.hash = name;
    const greetBlock = document.getElementById('sdTopbarGreet');
    const defaultBlock = document.getElementById('sdTopbarDefault');
    if (greetBlock && defaultBlock) {
        const onDashboard = (name === 'dashboard');
        greetBlock.style.display = onDashboard ? '' : 'none';
        defaultBlock.style.display = onDashboard ? 'none' : '';
        if (onDashboard) sdSetGreeting();
    }
    sdLoadSection(name);
}
function sdLoadSection(name) {
    switch (name) {
        case 'dashboard': sdLoadSummary(); sdLoadAnalytics(); sdLoadOverviewExtras(); break;
        case 'products': sdLoadProducts(1); break;
        case 'stock': sdLoadStock(1); break;
        case 'orders': sdLoadOrders(1); break;
        case 'equipment': sdLoadEquipment(1); break;
        case 'rentalBookings': sdLoadRentalBookings(1); break;
        case 'sales': sdLoadPerformance(); break;
        case 'customers': sdLoadCustomers(); break;
        case 'reviews': sdLoadReviews(); break;
        case 'earnings': sdLoadEarnings(); break;
        case 'invoices': sdLoadInvoices(1); break;
        case 'notifications': sdLoadNotificationsPage(); break;
        case 'inactive': sdLoadInactiveProducts(1); sdLoadInactiveEquipment(1); break;
    }
}
function sdRenderAllCached() {
    // Re-render whatever section is visible (and the cards/sidebar labels) using cached data, so switching language never re-fetches.
    sdApplyStaticText();
    if (sdCache.ovPerf || sdCache.ovInvoices || sdCache.ovReviews) sdRenderOverviewExtras();
    const active = document.querySelector('.sd-section.active');
    if (!active) return;
    const name = active.id.replace('sec-', '');
    if (sdCache[name]) sdCache['render_' + name] && sdCache['render_' + name]();
}

document.addEventListener('DOMContentLoaded', () => {
    sdApplyStaticText();
    sdSetGreeting();
    window.pageLanguageCallback(localStorage.getItem('agri_lang') || 'en');
    document.querySelectorAll('.sd-nav-item[data-section]').forEach(item => {
        item.addEventListener('click', () => sdShowSection(item.dataset.section));
    });
    const sdSidebarEl = document.getElementById('sdSidebar');
    const sdScrimEl = document.getElementById('sdSidebarScrim');
    const sdSyncScrim = () => { if (sdScrimEl) sdScrimEl.classList.toggle('show', sdSidebarEl.classList.contains('open')); };
    document.getElementById('sdMenuToggle').addEventListener('click', () => { sdSidebarEl.classList.toggle('open'); sdSyncScrim(); });
    if (sdScrimEl) sdScrimEl.addEventListener('click', () => { sdSidebarEl.classList.remove('open'); sdSyncScrim(); });
    document.getElementById('sdBellBtn').addEventListener('click', sdToggleNotifPanel);

    const sdCollapseBtn = document.getElementById('sdSidebarCollapseBtn');
    if (sdCollapseBtn) {
        if (localStorage.getItem('sd_sidebar_collapsed') === '1') sdSidebarEl.classList.add('collapsed');
        sdCollapseBtn.addEventListener('click', () => {
            const collapsed = sdSidebarEl.classList.toggle('collapsed');
            localStorage.setItem('sd_sidebar_collapsed', collapsed ? '1' : '0');
        });
    }

    const startSection = (location.hash || '#dashboard').replace('#', '');
    sdShowSection(document.getElementById('sec-' + startSection) ? startSection : 'dashboard');
    sdPollNotifications();
    setInterval(sdPollNotifications, 45000);
});

/* ---------------------------------------------------------------- DASHBOARD: summary -> To Do list + Store Stats strip */
async function sdLoadSummary() {
    const r = await sdApi('summary');
    if (!r.success) return;
    sdCache.summary = r.data;
    sdCache.dashboard = true;
    sdCache.render_dashboard = function () { sdRenderSummary(); sdRenderAnalytics(); };
    sdRenderSummary();
}
function sdRenderSummary() {
    const d = sdCache.summary; if (!d) return;

    // "To Do List" — the 4 things a seller needs to act on today.
    const todo = [
        ['fa-file-invoice-dollar', d.pending_orders, 'todoUnpaid', 'orders'],
        ['fa-boxes-packing', d.pending_orders, 'todoToProcess', 'orders'],
        ['fa-truck-fast', d.completed_orders, 'todoShipped', 'orders'],
        ['fa-triangle-exclamation', d.out_of_stock, 'todoSoldOut', 'stock'],
    ];
    document.getElementById('sdTodoGrid').innerHTML = todo.map(t => `
        <div class="sd-ov-todo-item" onclick="sdShowSection('${t[3]}')">
            <i class="fa-solid ${t[0]}"></i>
            <div class="sd-ov-todo-value">${t[1] || 0}</div>
            <div class="sd-ov-todo-label">${sdEsc(sdT(t[2]))}</div>
        </div>`).join('');

    // Store stats strip — real numbers only (no invented trend %s, since
    // AgriCart doesn't track storefront visitor sessions).
    const stats = [
        ['fa-eye', sdCache.productViews || 0, 'statViews'],
        ['fa-cart-shopping', d.total_orders, 'statOrders'],
        ['fa-box-open', d.units_sold, 'statUnitsSold'],
        ['fa-star', (d.avg_rating || 0).toFixed ? d.avg_rating.toFixed(1) : d.avg_rating, 'statRating'],
    ];
    document.getElementById('sdStoreStats').innerHTML = stats.map(s => `
        <div class="sd-ov-stat-box"><i class="fa-solid ${s[0]}"></i>
            <div><div class="sd-ov-stat-value">${s[1]}</div><div class="sd-ov-stat-label">${sdEsc(sdT(s[2]))}</div></div>
        </div>`).join('');

    document.getElementById('sdAvailableFunds').textContent = sdMoney(sdCache.availableBalance || 0);

    if (d.pending_orders > 0) { document.getElementById('sdOrdersBadge').textContent = d.pending_orders; document.getElementById('sdOrdersBadge').classList.add('show'); }
    else { document.getElementById('sdOrdersBadge').classList.remove('show'); }
    const inactiveBadge = document.getElementById('sdInactiveBadge');
    const inactiveCount = (d.inactive_products || 0);
    if (inactiveCount > 0) { inactiveBadge.textContent = inactiveCount; inactiveBadge.classList.add('show'); }
    else { inactiveBadge.classList.remove('show'); }
}

/* ---------------------------------------------------------------- DASHBOARD: greeting + profile + top products + latest invoice + testimonial + customers */
function sdGreetingKey() {
    const h = new Date().getHours();
    if (h < 12) return 'ovGoodMorning';
    if (h < 17) return 'ovGoodAfternoon';
    return 'ovGoodEvening';
}
function sdInitials(name) {
    return (name || 'S').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}
function sdSetGreeting() {
    const key = sdGreetingKey();
    const greetText = sdT(key);
    const fallback = { ovGoodMorning: 'Good Morning', ovGoodAfternoon: 'Good Afternoon', ovGoodEvening: 'Good Evening' };
    const prefixEl = document.getElementById('sdGreetPrefix');
    if (prefixEl) prefixEl.textContent = (greetText === key) ? fallback[key] : greetText;
    const displayName = (SD_SELLER_NAME && String(SD_SELLER_NAME).trim()) ? SD_SELLER_NAME : sdT('sellerRole');
    const nameEl = document.getElementById('sdGreetName');
    if (nameEl) nameEl.textContent = displayName;
    const initials = sdInitials(displayName);
    const sideName = document.getElementById('sdSidebarProfileName');
    if (sideName) sideName.textContent = displayName;
    const sideAvatar = document.getElementById('sdSidebarAvatar');
    if (sideAvatar) sideAvatar.textContent = initials;
    const topAvatar = document.getElementById('sdTopbarAvatar');
    if (topAvatar) topAvatar.textContent = initials;
    return displayName;
}
async function sdLoadOverviewExtras() {
    const [perf, invoices, reviews, customers, earnings] = await Promise.all([
        sdApi('product_performance'), sdApi('orders_list', { page: 1 }),
        sdApi('reviews_list'), sdApi('customers_list'), sdApi('earnings_summary'),
    ]);
    if (perf.success) sdCache.ovPerf = perf.data;
    if (invoices.success) sdCache.ovInvoices = invoices.data;
    if (reviews.success) sdCache.ovReviews = reviews.data;
    if (customers.success) sdCache.ovCustomerCount = customers.data.length;
    if (earnings.success) sdCache.availableBalance = earnings.data.available_balance;
    sdRenderOverviewExtras();
}
function sdRenderOverviewExtras() {
    const displayName = sdSetGreeting();
    document.getElementById('sdOvName').textContent = displayName;
    document.getElementById('sdOvAvatar').textContent = sdInitials(displayName);

    if (sdCache.ovPerf) {
        const perfData = sdCache.ovPerf;
        sdCache.productViews = perfData.reduce((sum, p) => sum + (parseInt(p.views_count) || 0), 0);
        const top = [...perfData].sort((a, b) => (b.revenue - a.revenue)).slice(0, 5);
        document.getElementById('sdTopProducts').innerHTML = top.length ? top.map((p, i) => `
            <div class="sd-ov-topprod-card" onclick="sdShowSection('products')">
                <div class="sd-ov-topprod-rank">#${i + 1}</div>
                <img src="${sdImgUrl(p.image)}" alt="${sdEsc(sdLocalName(p))}" onerror="this.onerror=null;this.src=SD_IMG_FALLBACK">
                <div class="sd-ov-topprod-name">${sdEsc(sdLocalName(p))}</div>
            </div>`).join('') : sdEmptyState('emptyProducts', 'emptyProductsSub', 'fa-box-open');
    }

    if (sdCache.ovInvoices) {
        const rows = sdCache.ovInvoices.slice(0, 5);
        const table = document.getElementById('sdLatestInvoiceTable');
        if (!rows.length) { table.innerHTML = sdEmptyState('emptyOrders', 'emptyOrdersSub', 'fa-file-invoice'); }
        else {
            table.innerHTML = `<thead><tr><th>${sdEsc(sdT('thInvoice'))}</th><th>${sdEsc(sdT('thBuyer'))}</th><th>${sdEsc(sdT('thDate'))}</th>
                <th>${sdEsc(sdT('thOrderStatus'))}</th><th></th></tr></thead>
                <tbody>${rows.map(o => `<tr>
                    <td><div class="sd-inv-prod-row">
                        <img class="sd-inv-thumb" src="${sdImgUrl(o.product_image)}" onerror="this.onerror=null;this.src=SD_IMG_FALLBACK">
                        <div><div class="sd-inv-id-chip">#${o.order_id}</div><div class="sd-inv-prod-name">${sdEsc(o.product_name)}</div></div>
                    </div></td>
                    <td><div class="sd-inv-buyer"><div class="sd-ov-avatar sd-ov-avatar-sm">${sdInitials(o.buyer_name)}</div>${sdEsc(o.buyer_name || '-')}</div></td>
                    <td><div class="sd-inv-date"><i class="fa-regular fa-calendar"></i>${sdEsc(new Date(o.order_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }))}</div></td>
                    <td><span class="sd-badge sd-badge-${o.item_status === 'delivered' ? 'green' : (o.item_status === 'cancelled' || o.item_status === 'returned' || o.item_status === 'refunded' ? 'danger' : 'orange')}">${sdEsc(sdT(SD_ORDER_STATUS_KEYS[o.item_status] || o.item_status))}</span></td>
                    <td><button class="sd-btn sd-btn-outline sd-inv-view-btn" title="${sdEsc(sdT('view'))}" onclick="sdViewInvoice(${o.item_id})"><i class="fa-solid fa-file-invoice"></i></button></td>
                </tr>`).join('')}</tbody>`;
        }
    }

    if (sdCache.ovReviews) {
        const box = document.getElementById('sdOvTestimonial');
        if (!sdCache.ovReviews.length) { box.innerHTML = `<div class="sd-empty" style="padding:16px 0"><i class="fa-solid fa-comment-slash"></i>${sdEsc(sdT('emptyReviews'))}</div>`; }
        else {
            const rv = sdCache.ovReviews[0];
            box.innerHTML = `<div class="sd-ov-testimonial">
                <div class="sd-ov-testimonial-head">
                    <div class="sd-ov-avatar sd-ov-avatar-sm">${sdInitials(rv.buyer_name)}</div>
                    <div><div style="font-weight:700;font-size:13px">${sdEsc(rv.buyer_name || 'Buyer')}</div><div class="sd-stars">${sdStarsHtml(rv.rating)}</div></div>
                </div>
                <p class="sd-ov-testimonial-text">"${sdEsc((rv.review_text || '').slice(0, 140))}"</p>
            </div>`;
        }
    }

    if (sdCache.ovCustomerCount != null) { document.getElementById('sdOvCustomerCount').textContent = sdCache.ovCustomerCount; }
    if (sdCache.availableBalance != null) { document.getElementById('sdAvailableFunds').textContent = sdMoney(sdCache.availableBalance); }
    if (sdCache.summary) sdRenderSummary();
}

/* ---------------------------------------------------------------- DASHBOARD: analytics */
let sdChart = null;
function sdOnRangeChange() {
    const range = document.getElementById('sdAnalyticsRange').value;
    const custom = range === 'custom';
    document.getElementById('sdCustomRangeRow').style.display = custom ? 'flex' : 'none';
    if (!custom) sdLoadAnalytics();
}
async function sdLoadAnalytics() {
    const range = document.getElementById('sdAnalyticsRange').value;
    const params = { range };
    if (range === 'custom') { params.start = document.getElementById('sdRangeStart').value; params.end = document.getElementById('sdRangeEnd').value; }
    const r = await sdApi('analytics', params);
    if (!r.success) return;
    sdCache.analytics = r.data;
    sdRenderAnalytics();
}
function sdRenderAnalytics() {
    const d = sdCache.analytics; if (!d) return;
    const labels = d.series.map(s => {
        const parts = s.d.split('-'); // 'YYYY-MM-DD' -> 'DD Mon' for a compact, readable axis label
        return parts.length === 3 ? `${parts[2]} ${['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][parseInt(parts[1],10)-1]}` : s.d;
    });
    const units = d.series.map(s => Number(s.units));
    const revenue = d.series.map(s => Number(s.revenue));

    const ctx = document.getElementById('sdSalesChart').getContext('2d');
    if (sdChart) sdChart.destroy();
    sdChart = new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [
            { type: 'line', label: sdT('thRevenue'), data: revenue, borderColor: '#D98E2B', backgroundColor: '#D98E2B', yAxisID: 'y1', tension: .3, pointRadius: 3 },
            // maxBarThickness keeps a single sparse day from stretching into
            // one giant block that fills the whole chart width.
            { type: 'bar', label: sdT('thQty'), data: units, backgroundColor: '#3F8F5F', yAxisID: 'y', maxBarThickness: 42, categoryPercentage: 0.5, barPercentage: 0.8 },
        ]},
        options: { responsive: true, maintainAspectRatio: false, scales: {
            y: { position: 'left', beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: sdT('thQty') } },
            y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: '₹' } },
        }},
    });
}

/* ---------------------------------------------------------------- PRODUCTS */
let sdProductsPage = 1;
async function sdLoadProducts(page) {
    sdProductsPage = page || sdProductsPage;
    const search = document.getElementById('sdProdSearch').value;
    const r = await sdApi('products_list', { search, page: sdProductsPage });
    if (!r.success) return;
    sdCache.products = r;
    sdCache.render_products = () => sdRenderProducts(r);
    sdRenderProducts(r);
}
function sdStockBadge(status) {
    const map = { in_stock: ['sd-badge-green','stInStock'], low_stock: ['sd-badge-orange','stLowStock'], out_of_stock: ['sd-badge-danger','stOutOfStock'] };
    const m = map[status] || map.in_stock;
    return `<span class="sd-badge ${m[0]}">${sdEsc(sdT(m[1]))}</span>`;
}
function sdApprovalBadge(status) {
    const map = { pending: ['sd-badge-orange','stApprovalPending'], approved: ['sd-badge-green','stApprovalApproved'], rejected: ['sd-badge-danger','stApprovalRejected'] };
    const m = map[status] || map.approved;
    return `<span class="sd-badge ${m[0]}">${sdEsc(sdT(m[1]))}</span>`;
}
const SD_IMG_FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 60">' +
    '<rect width="60" height="60" rx="10" fill="#EAF4EC"/>' +
    '<g fill="none" stroke="#3F8F5F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
    '<rect x="13" y="13" width="34" height="34" rx="4"/>' +
    '<circle cx="23" cy="24" r="2.6" fill="#3F8F5F" stroke="none"/>' +
    '<path d="M13 39l9-9 6 6 5-5 11 11"/>' +
    '</g></svg>'
);
function sdImgUrl(path) { return path ? '../' + path : SD_IMG_FALLBACK; }

function sdRenderProducts(r) {
    const table = document.getElementById('sdProductsTable');
    if (!r.data.length) { table.innerHTML = ''; document.getElementById('sdProductsPager').innerHTML = sdEmptyState('emptyProducts','emptyProductsSub','fa-box-open'); return; }
    table.innerHTML = `<thead><tr>
        <th>${sdEsc(sdT('thImage'))}</th><th>${sdEsc(sdT('thName'))}</th><th>${sdEsc(sdT('thCategory'))}</th>
        <th>${sdEsc(sdT('thOriginalStock'))}</th><th>${sdEsc(sdT('thSold'))}</th><th>${sdEsc(sdT('thRemaining'))}</th>
        <th>${sdEsc(sdT('thPrice'))}</th><th>${sdEsc(sdT('thStatus'))}</th><th>${sdEsc(sdT('thActions'))}</th></tr></thead>
        <tbody>${r.data.map(p => `<tr>
            <td><img class="sd-prod-thumb" src="${sdImgUrl(p.image)}" alt="" onerror="this.onerror=null;this.src=SD_IMG_FALLBACK"></td>
            <td style="font-weight:700">${sdEsc(sdProdName(p))} ${sdApprovalBadge(p.approval_status)}</td>
            <td>${sdEsc(p.category)}</td>
            <td>${p.original_stock}</td><td>${p.sold_quantity}</td><td>${p.remaining_stock}</td>
            <td>${sdMoney(p.price)}</td><td>${sdStockBadge(p.stock_status)}</td>
            <td><div class="sd-row-actions">
                <button class="sd-btn sd-btn-outline" onclick="sdOpenEdit(${p.id})"><i class="fa-solid fa-pen"></i> ${sdEsc(sdT('edit'))}</button>
                <button class="sd-btn sd-btn-green" onclick="sdOpenStock(${p.id}, ${p.remaining_stock})"><i class="fa-solid fa-warehouse"></i> ${sdEsc(sdT('updateStockBtn'))}</button>
                <button class="sd-btn sd-btn-danger" onclick="sdOpenDelete(${p.id})"><i class="fa-solid fa-ban"></i> ${sdEsc(sdT('deactivate'))}</button>
            </div></td>
        </tr>`).join('')}</tbody>`;
    document.getElementById('sdProductsPager').innerHTML = sdPagerHtml(r, sdLoadProducts);
}
function sdEmptyState(titleKey, subKey, icon) {
    return `<div class="sd-empty"><i class="fa-solid ${icon}"></i><div class="sd-empty-title">${sdEsc(sdT(titleKey))}</div>${subKey ? `<div>${sdEsc(sdT(subKey))}</div>` : ''}</div>`;
}
function sdPagerHtml(r, fn) {
    const pages = Math.max(1, Math.ceil(r.total / r.per_page));
    if (pages <= 1) return '';
    let html = '';
    for (let i = 1; i <= pages; i++) html += `<button class="${i === r.page ? 'active' : ''}" onclick="(${fn.name})(${i})">${i}</button>`;
    return html;
}

/* ---------------------------------------------------------------- STOCK (reuses products table with only stock-relevant columns) */
async function sdLoadStock(page) {
    sdProductsPage = page || sdProductsPage;
    const r = await sdApi('products_list', { page: sdProductsPage });
    if (!r.success) return;
    sdCache.stock = r;
    sdCache.render_stock = () => sdRenderStock(r);
    sdRenderStock(r);
}
function sdRenderStock(r) {
    const table = document.getElementById('sdStockTable');
    if (!r.data.length) { table.innerHTML = ''; document.getElementById('sdStockPager').innerHTML = sdEmptyState('emptyProducts', null, 'fa-warehouse'); return; }
    table.innerHTML = `<thead><tr><th>${sdEsc(sdT('thImage'))}</th><th>${sdEsc(sdT('thName'))}</th>
        <th>${sdEsc(sdT('thOriginalStock'))}</th><th>${sdEsc(sdT('thSold'))}</th><th>${sdEsc(sdT('thRemaining'))}</th>
        <th>${sdEsc(sdT('thStatus'))}</th><th>${sdEsc(sdT('thActions'))}</th></tr></thead>
        <tbody>${r.data.map(p => `<tr>
            <td><img class="sd-prod-thumb" src="${sdImgUrl(p.image)}" alt="" onerror="this.onerror=null;this.src=SD_IMG_FALLBACK"></td>
            <td style="font-weight:700">${sdEsc(sdProdName(p))}</td>
            <td>${p.original_stock}</td><td>${p.sold_quantity}</td><td>${p.remaining_stock}</td>
            <td>${sdStockBadge(p.stock_status)}</td>
            <td><button class="sd-btn sd-btn-green" onclick="sdOpenStock(${p.id}, ${p.remaining_stock})"><i class="fa-solid fa-warehouse"></i> ${sdEsc(sdT('updateStockBtn'))}</button></td>
        </tr>`).join('')}</tbody>`;
    document.getElementById('sdStockPager').innerHTML = sdPagerHtml(r, sdLoadStock);
}

/* ---------------------------------------------------------------- Edit / Stock / Delete modals */
async function sdOpenEdit(id) {
    const r = await sdApi('product_get', { product_id: id });
    if (!r.success) { sdToast(sdT('toastError')); return; }
    const p = r.data;
    document.getElementById('sdEditId').value = p.id;
    document.getElementById('sdEditName').value = p.name;
    document.getElementById('sdEditPrice').value = p.price;
    document.getElementById('sdEditCategory').value = p.category;
    document.getElementById('sdEditBrand').value = p.brand || '';
    document.getElementById('sdEditDesc').value = p.description || '';
    document.getElementById('sdEditCondition').value = p.product_condition || 'new';
    document.getElementById('sdEditDelivery').value = p.delivery_available ? '1' : '0';
    document.getElementById('sdEditLowStockLimit').value = (p.low_stock_limit != null) ? p.low_stock_limit : 5;
    sdOpenModal('sdEditModal');
}
async function sdSaveEditProduct() {
    const r = await sdApi('product_edit_save', {
        product_id: document.getElementById('sdEditId').value,
        name: document.getElementById('sdEditName').value,
        price: document.getElementById('sdEditPrice').value,
        category: document.getElementById('sdEditCategory').value,
        brand: document.getElementById('sdEditBrand').value,
        description: document.getElementById('sdEditDesc').value,
        product_condition: document.getElementById('sdEditCondition').value,
        delivery_available: document.getElementById('sdEditDelivery').value,
        low_stock_limit: document.getElementById('sdEditLowStockLimit').value,
    }, 'POST');
    sdCloseModal('sdEditModal');
    sdToast(r.success ? sdT('toastSaved') : sdT('toastError'));
    if (r.success) { sdLoadProducts(sdProductsPage); sdLoadStock(sdProductsPage); }
}
function sdOpenStock(id, currentStock) {
    document.getElementById('sdStockProdId').value = id;
    document.getElementById('sdStockCurrent').value = currentStock;
    document.getElementById('sdStockMode').value = 'add';
    document.getElementById('sdStockValue').value = '';
    sdOpenModal('sdStockModal');
}
async function sdSaveStock() {
    const r = await sdApi('product_update_stock', {
        product_id: document.getElementById('sdStockProdId').value,
        mode: document.getElementById('sdStockMode').value,
        value: document.getElementById('sdStockValue').value,
    }, 'POST');
    sdCloseModal('sdStockModal');
    sdToast(r.success ? sdT('toastSaved') : sdT('toastError'));
    if (r.success) { sdLoadProducts(sdProductsPage); sdLoadStock(sdProductsPage); sdLoadSummary(); }
}
function sdOpenDelete(id) { document.getElementById('sdDeleteId').value = id; sdOpenModal('sdDeleteModal'); }
async function sdConfirmDelete() {
    const r = await sdApi('product_delete', { product_id: document.getElementById('sdDeleteId').value }, 'POST');
    sdCloseModal('sdDeleteModal');
    sdToast(r.success ? sdT('toastDeactivated') : sdT('toastError'));
    if (r.success) { sdLoadProducts(sdProductsPage); sdLoadStock(sdProductsPage); sdLoadSummary(); }
}

/* ---------------------------------------------------------------- INACTIVE LISTINGS */
let sdInactiveProductsPage = 1, sdInactiveEquipmentPage = 1;
async function sdLoadInactiveProducts(page) {
    sdInactiveProductsPage = page || sdInactiveProductsPage;
    const r = await sdApi('products_list_inactive', { page: sdInactiveProductsPage });
    if (!r.success) return;
    sdCache.inactiveProducts = r;
    sdCache.render_inactive = sdRenderInactiveAll;
    sdRenderInactiveProducts(r);
}
function sdRenderInactiveProducts(r) {
    const table = document.getElementById('sdInactiveProductsTable');
    if (!r.data.length) { table.innerHTML = ''; document.getElementById('sdInactiveProductsPager').innerHTML = sdEmptyState('emptyInactiveProducts','emptyInactiveProductsSub','fa-box-open'); return; }
    table.innerHTML = `<thead><tr>
        <th>${sdEsc(sdT('thImage'))}</th><th>${sdEsc(sdT('thName'))}</th><th>${sdEsc(sdT('thCategory'))}</th>
        <th>${sdEsc(sdT('thPrice'))}</th><th>${sdEsc(sdT('thActions'))}</th></tr></thead>
        <tbody>${r.data.map(p => `<tr>
            <td><img class="sd-prod-thumb" src="${sdImgUrl(p.image)}" alt="" onerror="this.onerror=null;this.src=SD_IMG_FALLBACK"></td>
            <td style="font-weight:700">${sdEsc(sdProdName(p))}</td>
            <td>${sdEsc(p.category)}</td>
            <td>${sdMoney(p.price)}</td>
            <td><button class="sd-btn sd-btn-green" onclick="sdOpenRestoreProduct(${p.id})"><i class="fa-solid fa-rotate-left"></i> ${sdEsc(sdT('restore'))}</button></td>
        </tr>`).join('')}</tbody>`;
    document.getElementById('sdInactiveProductsPager').innerHTML = sdPagerHtml(r, sdLoadInactiveProducts);
}
function sdOpenRestoreProduct(id) { document.getElementById('sdRestoreId').value = id; sdOpenModal('sdRestoreModal'); }
async function sdConfirmRestoreProduct() {
    const r = await sdApi('product_restore', { product_id: document.getElementById('sdRestoreId').value }, 'POST');
    sdCloseModal('sdRestoreModal');
    sdToast(r.success ? sdT('toastRestored') : sdT('toastError'));
    if (r.success) { sdLoadInactiveProducts(sdInactiveProductsPage); sdLoadProducts(sdProductsPage); sdLoadSummary(); }
}

async function sdLoadInactiveEquipment(page) {
    sdInactiveEquipmentPage = page || sdInactiveEquipmentPage;
    const r = await sdApi('equipment_list', { page: sdInactiveEquipmentPage, view: 'inactive' });
    if (!r.success) return;
    sdCache.inactiveEquipment = r;
    sdCache.render_inactive = sdRenderInactiveAll;
    sdRenderInactiveEquipment(r);
}
function sdRenderInactiveEquipment(r) {
    const table = document.getElementById('sdInactiveEquipmentTable');
    if (!r.data.length) { table.innerHTML = ''; document.getElementById('sdInactiveEquipmentPager').innerHTML = sdEmptyState('emptyInactiveEquipment','emptyInactiveEquipmentSub','fa-tractor'); return; }
    table.innerHTML = `<thead><tr>
        <th>${sdEsc(sdT('thEquipImage'))}</th><th>${sdEsc(sdT('thEquipName'))}</th><th>${sdEsc(sdT('thType'))}</th>
        <th>${sdEsc(sdT('thRentDay'))}</th><th>${sdEsc(sdT('thActions'))}</th></tr></thead>
        <tbody>${r.data.map(e => `<tr>
            <td><img class="sd-prod-thumb" src="${sdImgUrl(e.image)}" alt="" onerror="this.onerror=null;this.src=SD_IMG_FALLBACK"></td>
            <td style="font-weight:700">${sdEsc(sdLocalName(e))}</td>
            <td>${sdEsc(e.type)}</td>
            <td>${sdMoney(e.rent_per_day)}</td>
            <td><button class="sd-btn sd-btn-green" onclick="sdOpenActivateEquipment(${e.id})"><i class="fa-solid fa-rotate-left"></i> ${sdEsc(sdT('restore'))}</button></td>
        </tr>`).join('')}</tbody>`;
    document.getElementById('sdInactiveEquipmentPager').innerHTML = sdPagerHtml(r, sdLoadInactiveEquipment);
}
function sdOpenActivateEquipment(id) { document.getElementById('sdEquipActivateId').value = id; sdOpenModal('sdEquipActivateModal'); }
async function sdConfirmActivateEquipment() {
    const r = await sdApi('equipment_activate', { id: document.getElementById('sdEquipActivateId').value }, 'POST');
    sdCloseModal('sdEquipActivateModal');
    sdToast(r.success ? sdT('toastRestored') : sdT('toastError'));
    if (r.success) { sdLoadInactiveEquipment(sdInactiveEquipmentPage); sdLoadEquipment(sdEquipPage); }
}
function sdRenderInactiveAll() {
    if (sdCache.inactiveProducts) sdRenderInactiveProducts(sdCache.inactiveProducts);
    if (sdCache.inactiveEquipment) sdRenderInactiveEquipment(sdCache.inactiveEquipment);
}

/* ---------------------------------------------------------------- ORDERS */
let sdOrdersPage = 1;
async function sdLoadOrders(page) {
    sdOrdersPage = page || sdOrdersPage;
    const r = await sdApi('orders_list', {
        search: document.getElementById('sdOrderSearch').value,
        status: document.getElementById('sdOrderStatus').value,
        date_from: document.getElementById('sdOrderFrom').value,
        date_to: document.getElementById('sdOrderTo').value,
        page: sdOrdersPage,
    });
    if (!r.success) return;
    sdCache.orders = r;
    sdCache.render_orders = () => sdRenderOrders(r);
    sdRenderOrders(r);
}
const SD_ORDER_STATUS_KEYS = { new_order:'stNewOrder', confirmed:'stConfirmed', packed:'stPacked', shipped:'stShipped', delivered:'stDelivered', cancelled:'stCancelled', returned:'stReturned', refunded:'stRefunded' };
const SD_ORDER_STATUS_BADGE = { new_order:'sd-badge-orange', confirmed:'sd-badge-green', packed:'sd-badge-green', shipped:'sd-badge-green', delivered:'sd-badge-green', cancelled:'sd-badge-danger', returned:'sd-badge-danger', refunded:'sd-badge-danger' };
// Mirrors includes/seller_functions.php -> agri_seller_order_status_transitions().
// This only decides which options the dropdown SHOWS — the server validates
// every transition again independently and rejects anything not in its own map.
const SD_ORDER_TRANSITIONS = {
    new_order: ['confirmed', 'cancelled'],
    confirmed: ['packed', 'cancelled'],
    packed: ['shipped', 'cancelled'],
    shipped: ['delivered'],
    delivered: ['returned'],
    returned: ['refunded'],
    cancelled: [],
    refunded: [],
};
function sdOrderStatusOptions(current) {
    const options = [current, ...(SD_ORDER_TRANSITIONS[current] || [])];
    return options.map(s => `<option value="${s}" ${s === current ? 'selected' : ''}>${sdEsc(sdT(SD_ORDER_STATUS_KEYS[s]))}</option>`).join('');
}
function sdRenderOrders(r) {
    const table = document.getElementById('sdOrdersTable');
    if (!r.data.length) { table.innerHTML = ''; document.getElementById('sdOrdersPager').innerHTML = sdEmptyState('emptyOrders','emptyOrdersSub','fa-truck-fast'); return; }
    table.innerHTML = `<thead><tr>
        <th>${sdEsc(sdT('thOrderId'))}</th><th>${sdEsc(sdT('thBuyer'))}</th><th>${sdEsc(sdT('thProduct'))}</th>
        <th>${sdEsc(sdT('thQty'))}</th><th>${sdEsc(sdT('thPrice'))}</th><th>${sdEsc(sdT('thTotal'))}</th>
        <th>${sdEsc(sdT('thCharges'))}</th><th>${sdEsc(sdT('thNet'))}</th><th>${sdEsc(sdT('thDate'))}</th>
        <th>${sdEsc(sdT('thPayment'))}</th><th>${sdEsc(sdT('thOrderStatus'))}</th><th>${sdEsc(sdT('thInvoice'))}</th></tr></thead>
        <tbody>${r.data.map(o => `<tr>
            <td>#${o.order_id}</td>
            <td><div class="sd-prod-cell"><img class="sd-avatar-sm" src="${o.buyer_avatar ? '../' + o.buyer_avatar : 'https://ui-avatars.com/api/?background=EAF4EC&color=3F8F5F&name=' + encodeURIComponent(o.buyer_name||'?')}" alt="">${sdEsc(o.buyer_name || '-')}</div></td>
            <td><div class="sd-prod-cell"><img class="sd-prod-thumb" src="${sdImgUrl(o.product_image)}" alt="" onerror="this.onerror=null;this.src=SD_IMG_FALLBACK">${sdEsc(sdLocalName(o, 'product_name'))}</div></td>
            <td>${o.qty}</td><td>${sdMoney(o.price)}</td><td>${sdMoney(o.total_amount)}</td>
            <td>${sdMoney(o.platform_charge_amount)}</td><td>${sdMoney(o.seller_net_amount)}</td>
            <td>${sdEsc(o.order_date)}</td><td>${sdEsc(o.payment_status || '-')}</td>
            <td><select class="sd-select" style="min-width:120px" data-item="${o.item_id}" data-prev="${o.item_status}" onchange="sdUpdateOrderStatus(${o.item_id}, this.value, this)">${sdOrderStatusOptions(o.item_status)}</select></td>
            <td>
                <button class="sd-btn sd-btn-outline" onclick="sdViewOrderDetails(${o.item_id})" title="${sdEsc(sdT('view'))}"><i class="fa-solid fa-clock-rotate-left"></i></button>
                <button class="sd-btn sd-btn-outline" onclick="sdViewInvoice(${o.item_id})"><i class="fa-solid fa-file-invoice"></i> ${sdEsc(sdT('view'))}</button>
            </td>
        </tr>`).join('')}</tbody>`;
    document.getElementById('sdOrdersPager').innerHTML = sdPagerHtml(r, sdLoadOrders);
}
// Statuses important/irreversible enough to require an explicit confirmation
// before the request is even sent — mirrors the "Confirmation modal before
// important actions" requirement without a full custom modal component.
const SD_ORDER_STATUS_CONFIRM = {
    cancelled: 'Cancel this order item? This cannot be undone and will restore stock.',
    returned: 'Mark this item as Returned? This will reverse the seller earning for it.',
    delivered: 'Mark this item as Delivered? The buyer will be notified immediately.',
};
async function sdUpdateOrderStatus(itemId, status, selectEl) {
    const prev = selectEl ? selectEl.dataset.prev : null;
    const msg = SD_ORDER_STATUS_CONFIRM[status];
    if (msg && !confirm(msg)) {
        if (selectEl && prev) selectEl.value = prev;
        return;
    }
    if (selectEl) selectEl.disabled = true;
    const r = await sdApi('order_update_status', { item_id: itemId, status }, 'POST');
    sdToast(r.success ? sdT('toastUpdated') : (r.error || sdT('toastError')));
    if (r.success) {
        if (selectEl) selectEl.dataset.prev = status;
        sdLoadOrders(sdOrdersPage); sdLoadSummary();
    } else if (selectEl && prev) {
        selectEl.value = prev; // revert the dropdown on a rejected/invalid transition
    }
    if (selectEl) selectEl.disabled = false;
}
async function sdViewOrderDetails(itemId) {
    const [invRes, histRes] = await Promise.all([
        sdApi('order_invoice', { item_id: itemId }),
        sdApi('order_get_history', { item_id: itemId }),
    ]);
    if (!invRes.success) { sdToast(sdT('toastError')); return; }
    const o = invRes.data;
    const hist = (histRes.success ? histRes.data : []) || [];
    const timelineHtml = hist.length
        ? `<div class="sd-timeline">` + hist.map(h => `
            <div class="sd-timeline-row">
                <div class="sd-timeline-dot"></div>
                <div class="sd-timeline-body">
                    <strong>${sdEsc(h.new_status_label || h.new_status)}</strong>
                    <div style="font-size:11.5px;color:var(--sd-muted)">
                        ${sdEsc((h.changed_by_role || 'system').replace(/^\w/, c => c.toUpperCase()))}${h.changed_by_name ? ' · ' + sdEsc(h.changed_by_name) : ''} · ${sdEsc(h.created_at || '')}
                        ${h.reason ? '<br>' + sdEsc(h.reason) : ''}
                    </div>
                </div>
            </div>`).join('') + `</div>`
        : `<p style="color:var(--sd-muted);font-size:12.5px">${sdEsc(sdT('emptyOrders') || 'No history yet.')}</p>`;

    document.getElementById('sdInvoiceContent').innerHTML = `
        <h3><i class="fa-solid fa-truck-fast"></i> Order #${o.order_id} — Item #${o.item_id}</h3>
        <p><strong>${sdEsc(sdT('thBuyer'))}:</strong> ${sdEsc(o.buyer_name || '-')} (${sdEsc(o.buyer_mobile || '-')})</p>
        <p><strong>${sdEsc(sdT('thProduct'))}:</strong> ${sdEsc(o.product_name)}</p>
        <p><strong>${sdEsc(sdT('thQty'))}:</strong> ${o.qty} &times; ${sdMoney(o.price)} = ${sdMoney(o.total_amount)}</p>
        <p><strong>Current Status:</strong> ${sdEsc(sdT(SD_ORDER_STATUS_KEYS[o.item_status]) || o.item_status)}</p>
        ${o.delivery_address ? `<p><strong>${sdEsc(sdT('thLocation'))}:</strong> ${sdEsc(o.delivery_address)}</p>` : ''}
        <h4 style="margin-top:14px">Status Timeline</h4>
        ${timelineHtml}
        <div class="sd-modal-actions"><button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdInvoiceModal')">${sdEsc(sdT('invoiceClose'))}</button></div>`;
    sdOpenModal('sdInvoiceModal');
}
async function sdViewInvoice(itemId) {
    const r = await sdApi('order_invoice', { item_id: itemId });
    if (!r.success) { sdToast(sdT('toastError')); return; }
    const o = r.data;
    document.getElementById('sdInvoiceContent').innerHTML = `
        <h3><i class="fa-solid fa-file-invoice"></i> ${sdEsc(sdT('invoiceTitle'))} #${o.order_id}</h3>
        <p><strong>${sdEsc(sdT('invoiceOrderId'))}:</strong> ${o.item_id}</p>
        <p><strong>${sdEsc(sdT('thBuyer'))}:</strong> ${sdEsc(o.buyer_name || '-')} (${sdEsc(o.buyer_mobile || '-')})</p>
        <p><strong>${sdEsc(sdT('thProduct'))}:</strong> ${sdEsc(o.product_name)}</p>
        <p><strong>${sdEsc(sdT('thQty'))}:</strong> ${o.qty} &times; ${sdMoney(o.price)} = ${sdMoney(o.total_amount)}</p>
        <p><strong>${sdEsc(sdT('thCharges'))}:</strong> ${sdMoney(o.platform_charge_amount)}</p>
        <p><strong>${sdEsc(sdT('thNet'))}:</strong> ${sdMoney(o.seller_net_amount)}</p>
        <p><strong>${sdEsc(sdT('thDate'))}:</strong> ${sdEsc(o.order_date)}</p>
        ${o.delivery_address ? `<p><strong>${sdEsc(sdT('thLocation'))}:</strong> ${sdEsc(o.delivery_address)}</p>` : ''}
        <div class="sd-modal-actions"><button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdInvoiceModal')">${sdEsc(sdT('invoiceClose'))}</button>
        <a class="sd-btn sd-btn-outline" href="../pages/invoice.php?order_id=${o.order_id}" target="_blank" rel="noopener"><i class="fa-solid fa-file-invoice"></i> ${sdEsc(sdT('view'))} (Buyer)</a>
        <a class="sd-btn sd-btn-green" href="invoice.php?order_id=${o.order_id}" target="_blank" rel="noopener"><i class="fa-solid fa-file-invoice-dollar"></i> Seller Invoice</a></div>`;
    sdOpenModal('sdInvoiceModal');
}

/* ---------------------------------------------------------------- SALES INVOICES */
let sdInvoicesPage = 1;
async function sdLoadInvoices(page) {
    sdInvoicesPage = page || sdInvoicesPage;
    const r = await sdApi('seller_invoices_list', {
        search: document.getElementById('sdInvoiceSearch').value,
        payment_status: document.getElementById('sdInvoicePaymentStatus').value,
        settlement_status: document.getElementById('sdInvoiceSettlementStatus').value,
        date_from: document.getElementById('sdInvoiceFrom').value,
        date_to: document.getElementById('sdInvoiceTo').value,
        page: sdInvoicesPage,
    });
    if (!r.success) return;
    sdCache.invoices = r;
    sdCache.render_invoices = () => sdRenderInvoices(r);
    sdRenderInvoices(r);
}
const SD_SETTLEMENT_LABEL = { pending: 'Pending', available: 'Ready for Payout', paid: 'Paid Out' };
const SD_SETTLEMENT_BADGE = { pending: 'sd-badge-orange', available: 'sd-badge-green', paid: 'sd-badge-green' };
function sdRenderInvoices(r) {
    const table = document.getElementById('sdInvoicesTable');
    if (!r.data.length) { table.innerHTML = ''; document.getElementById('sdInvoicesPager').innerHTML = sdEmptyState('emptyOrders', 'emptyOrdersSub', 'fa-file-invoice-dollar'); return; }
    table.innerHTML = `<thead><tr>
        <th data-sd="thInvoice">Invoice No.</th><th data-sd="thOrderId">Order ID</th><th>Product Value</th>
        <th data-sd="thCharges">Platform Charges</th><th data-sd="thNet">Seller Net</th><th data-sd="thDate">Date</th>
        <th data-sd="thPayment">Payment</th><th>Settlement</th><th data-sd="thActions">Actions</th></tr></thead>
        <tbody>${r.data.map(inv => `<tr>
            <td>${sdEsc(inv.invoice_number)}</td>
            <td>#${inv.order_id}</td>
            <td>${sdMoney(inv.gross_amount)}</td>
            <td>${sdMoney(inv.platform_charge_amount)}</td>
            <td><b>${sdMoney(inv.net_amount)}</b></td>
            <td>${sdEsc(inv.generated_at)}</td>
            <td>${sdEsc(inv.payment_status || '-')}</td>
            <td><span class="sd-badge ${SD_SETTLEMENT_BADGE[inv.settlement_status] || 'sd-badge-orange'}">${sdEsc(SD_SETTLEMENT_LABEL[inv.settlement_status] || inv.settlement_status)}</span></td>
            <td>
                <a class="sd-btn sd-btn-outline" href="invoice.php?order_id=${inv.order_id}" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i> ${sdEsc(sdT('view'))}</a>
                <a class="sd-btn sd-btn-green" href="invoice.php?order_id=${inv.order_id}" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i></a>
            </td>
        </tr>`).join('')}</tbody>`;
    document.getElementById('sdInvoicesPager').innerHTML = sdPagerHtml(r, sdLoadInvoices);
}

/* ---------------------------------------------------------------- EQUIPMENT RENTAL */
let sdEquipPage = 1;
async function sdLoadEquipment(page) {
    sdEquipPage = page || sdEquipPage;
    const search = document.getElementById('sdEquipSearch').value;
    const r = await sdApi('equipment_list', { search, page: sdEquipPage });
    if (!r.success) return;
    sdCache.equipment = r;
    sdCache.render_equipment = () => sdRenderEquipment(r);
    sdRenderEquipment(r);
}
function sdEquipConditionBadge(c) {
    const map = { excellent: 'sd-badge-green', good: 'sd-badge-green', average: 'sd-badge-orange' };
    return `<span class="sd-badge ${map[c] || 'sd-badge-grey'}">${sdEsc(sdT('cond' + (c ? c[0].toUpperCase() + c.slice(1) : 'Good')))}</span>`;
}
const SD_EQUIP_STATUS_BADGE = { available: 'sd-badge-green', booked: 'sd-badge-orange', unavailable: 'sd-badge-grey', deactivated: 'sd-badge-danger' };
const SD_EQUIP_STATUS_KEY = { available: 'stAvailable', booked: 'stBooked', unavailable: 'stUnavailable', deactivated: 'stDeactivated' };
function sdEquipStatusBadge(status) {
    return `<span class="sd-badge ${SD_EQUIP_STATUS_BADGE[status] || 'sd-badge-grey'}">${sdEsc(sdT(SD_EQUIP_STATUS_KEY[status] || status))}</span>`;
}
function sdRenderEquipment(r) {
    const table = document.getElementById('sdEquipmentTable');
    if (!r.data.length) { table.innerHTML = ''; document.getElementById('sdEquipmentPager').innerHTML = sdEmptyState('emptyEquipment','emptyEquipmentSub','fa-tractor'); return; }
    table.innerHTML = `<thead><tr>
        <th>${sdEsc(sdT('thEquipImage'))}</th><th>${sdEsc(sdT('thEquipName'))}</th><th>${sdEsc(sdT('thType'))}</th>
        <th>${sdEsc(sdT('thRentDay'))}</th><th>${sdEsc(sdT('thCondition'))}</th><th>${sdEsc(sdT('thStatus'))}</th>
        <th>${sdEsc(sdT('thApproval'))}</th><th>${sdEsc(sdT('thActiveBookings'))}</th><th>${sdEsc(sdT('thActions'))}</th></tr></thead>
        <tbody>${r.data.map(e => `<tr>
            <td><img class="sd-prod-thumb" src="${sdImgUrl(e.image)}" alt="" onerror="this.onerror=null;this.src=SD_IMG_FALLBACK"></td>
            <td style="font-weight:700">${sdEsc(sdLocalName(e))}</td>
            <td>${sdEsc(e.type)}</td>
            <td>${sdMoney(e.rent_per_day)}</td>
            <td>${sdEquipConditionBadge(e.equipment_condition)}</td>
            <td>${sdEquipStatusBadge(e.status)}</td>
            <td>${sdApprovalBadge(e.approval_status)}</td>
            <td>${e.active_bookings}</td>
            <td><div class="sd-row-actions">
                <button class="sd-btn sd-btn-outline" onclick="sdOpenEquipmentEdit(${e.id})"><i class="fa-solid fa-pen"></i> ${sdEsc(sdT('edit'))}</button>
                <button class="sd-btn sd-btn-danger" onclick="sdOpenEquipmentDelete(${e.id})"><i class="fa-solid fa-ban"></i> ${sdEsc(sdT('deactivate'))}</button>
            </div></td>
        </tr>`).join('')}</tbody>`;
    document.getElementById('sdEquipmentPager').innerHTML = sdPagerHtml(r, sdLoadEquipment);
}
function sdOpenEquipmentAdd() {
    document.getElementById('sdEquipId').value = '';
    document.getElementById('sdEquipModalTitle').textContent = sdT('addEquipment');
    ['sdEquipName','sdEquipHp','sdEquipBrand','sdEquipModel','sdEquipCity','sdEquipDesc'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('sdEquipType').value = 'tractor';
    document.getElementById('sdEquipRent').value = '';
    document.getElementById('sdEquipCondition').value = 'good';
    document.getElementById('sdEquipDeposit').value = '';
    document.getElementById('sdEquipOperator').value = '0';
    document.getElementById('sdEquipFuel').value = '0';
    document.getElementById('sdEquipAvailability').value = '1';
    sdOpenModal('sdEquipModal');
}
async function sdOpenEquipmentEdit(id) {
    const r = await sdApi('equipment_get', { id });
    if (!r.success) { sdToast(sdT('toastError')); return; }
    const e = r.data;
    document.getElementById('sdEquipId').value = e.id;
    document.getElementById('sdEquipModalTitle').textContent = sdT('editEquipment');
    document.getElementById('sdEquipName').value = e.name;
    document.getElementById('sdEquipType').value = e.type || 'tractor';
    document.getElementById('sdEquipRent').value = e.rent_per_day;
    document.getElementById('sdEquipHp').value = e.hp || '';
    document.getElementById('sdEquipBrand').value = e.brand || '';
    document.getElementById('sdEquipModel').value = e.model || '';
    document.getElementById('sdEquipCondition').value = e.equipment_condition || 'good';
    document.getElementById('sdEquipDeposit').value = e.security_deposit || 0;
    document.getElementById('sdEquipOperator').value = e.operator_available ? '1' : '0';
    document.getElementById('sdEquipFuel').value = e.fuel_included ? '1' : '0';
    document.getElementById('sdEquipCity').value = e.city_name || '';
    document.getElementById('sdEquipAvailability').value = e.availability ? '1' : '0';
    document.getElementById('sdEquipDesc').value = e.description || '';
    sdOpenModal('sdEquipModal');
}
async function sdSaveEquipment() {
    const r = await sdApi('equipment_save', {
        id: document.getElementById('sdEquipId').value,
        name: document.getElementById('sdEquipName').value,
        type: document.getElementById('sdEquipType').value,
        rent_per_day: document.getElementById('sdEquipRent').value,
        hp: document.getElementById('sdEquipHp').value,
        brand: document.getElementById('sdEquipBrand').value,
        model: document.getElementById('sdEquipModel').value,
        equipment_condition: document.getElementById('sdEquipCondition').value,
        security_deposit: document.getElementById('sdEquipDeposit').value,
        operator_available: document.getElementById('sdEquipOperator').value,
        fuel_included: document.getElementById('sdEquipFuel').value,
        city: document.getElementById('sdEquipCity').value,
        availability: document.getElementById('sdEquipAvailability').value,
        description: document.getElementById('sdEquipDesc').value,
    }, 'POST');
    sdCloseModal('sdEquipModal');
    sdToast(r.success ? sdT('toastSaved') : (r.error || sdT('toastError')));
    if (r.success) sdLoadEquipment(sdEquipPage);
}
function sdOpenEquipmentDelete(id) { document.getElementById('sdEquipDeleteId').value = id; sdOpenModal('sdEquipDeleteModal'); }
async function sdConfirmDeleteEquipment() {
    const r = await sdApi('equipment_delete', { id: document.getElementById('sdEquipDeleteId').value }, 'POST');
    sdCloseModal('sdEquipDeleteModal');
    sdToast(r.success ? sdT('toastDeactivated') : sdT('toastError'));
    if (r.success) { sdLoadEquipment(sdEquipPage); sdLoadSummary(); }
}

/* ---------------------------------------------------------------- RENTAL BOOKINGS */
let sdRentalPage = 1;
async function sdLoadRentalBookings(page) {
    sdRentalPage = page || sdRentalPage;
    const r = await sdApi('rental_bookings_list', {
        search: document.getElementById('sdRentalSearch').value,
        status: document.getElementById('sdRentalStatus').value,
        date_from: document.getElementById('sdRentalFrom').value,
        date_to: document.getElementById('sdRentalTo').value,
        page: sdRentalPage,
    });
    if (!r.success) return;
    sdCache.rentalBookings = r;
    sdCache.render_rentalBookings = () => sdRenderRentalBookings(r);
    sdRenderRentalBookings(r);
    const pending = r.data.filter(b => b.status === 'pending').length;
    const badge = document.getElementById('sdRentalBadge');
    if (pending > 0) { badge.textContent = pending; badge.classList.add('show'); } else { badge.classList.remove('show'); }
}
const SD_RENTAL_STATUS_KEYS = { pending:'rbPending', confirmed:'rbConfirmed', on_the_way:'rbOnTheWay', completed:'rbCompleted', cancelled:'rbCancelled' };
const SD_RENTAL_STATUS_FLOW = ['pending','confirmed','on_the_way','completed','cancelled'];
function sdRentalStatusOptions(current) {
    return SD_RENTAL_STATUS_FLOW.map(s => `<option value="${s}" ${s === current ? 'selected' : ''}>${sdEsc(sdT(SD_RENTAL_STATUS_KEYS[s]))}</option>`).join('');
}
function sdRenderRentalBookings(r) {
    const table = document.getElementById('sdRentalTable');
    if (!r.data.length) { table.innerHTML = ''; document.getElementById('sdRentalPager').innerHTML = sdEmptyState('emptyRentalBookings','emptyRentalBookingsSub','fa-calendar-check'); return; }
    table.innerHTML = `<thead><tr>
        <th>${sdEsc(sdT('thBookingId'))}</th><th>${sdEsc(sdT('thEquipment'))}</th><th>${sdEsc(sdT('thCustomer2'))}</th>
        <th>${sdEsc(sdT('thRentalDates'))}</th><th>${sdEsc(sdT('thHours'))}</th><th>${sdEsc(sdT('thAmount2'))}</th>
        <th>${sdEsc(sdT('thBookingStatus'))}</th><th>${sdEsc(sdT('thPaymentStatus'))}</th></tr></thead>
        <tbody>${r.data.map(b => `<tr>
            <td>${sdEsc(b.booking_number || ('#' + b.id))}</td>
            <td><div class="sd-prod-cell"><img class="sd-prod-thumb" src="${sdImgUrl(b.equipment_image)}" alt="" onerror="this.onerror=null;this.src=SD_IMG_FALLBACK">${sdEsc(sdLocalName(b, 'equipment_name'))}</div></td>
            <td>${sdEsc(b.contact_name || '-')}${b.contact_mobile ? ' · ' + sdEsc(b.contact_mobile) : ''}</td>
            <td>${sdEsc(b.from_date)} → ${sdEsc(b.to_date)}</td>
            <td>${b.total_hours != null ? b.total_hours : '-'}</td>
            <td>${sdMoney(b.total_amount)}</td>
            <td><select class="sd-select" style="min-width:120px" onchange="sdUpdateRentalStatus(${b.id}, this.value)">${sdRentalStatusOptions(b.status)}</select></td>
            <td><span class="sd-badge ${b.payment_status==='paid'?'sd-badge-green':(b.payment_status==='failed'?'sd-badge-danger':'sd-badge-orange')}">${sdEsc(b.payment_status || '-')}</span></td>
        </tr>`).join('')}</tbody>`;
    document.getElementById('sdRentalPager').innerHTML = sdPagerHtml(r, sdLoadRentalBookings);
}
async function sdUpdateRentalStatus(bookingId, status) {
    const r = await sdApi('booking_update_status', { booking_id: bookingId, status }, 'POST');
    sdToast(r.success ? sdT('toastUpdated') : sdT('toastError'));
    if (r.success) sdLoadRentalBookings(sdRentalPage);
}

/* ---------------------------------------------------------------- SALES HISTORY / PERFORMANCE */
async function sdLoadPerformance() {
    const r = await sdApi('product_performance');
    if (!r.success) return;
    sdCache.performance = r.data;
    sdCache.render_sales = sdRenderPerformance;
    sdRenderPerformance();
}
function sdRenderPerformance() {
    const rows = sdCache.performance; if (!rows) return;
    const table = document.getElementById('sdPerfTable');
    if (!rows.length) { table.innerHTML = ''; document.getElementById('sdPerfHighlights').innerHTML = sdEmptyState('emptyProducts', null, 'fa-chart-line'); return; }

    const best = rows[0], worst = rows[rows.length - 1];
    document.getElementById('sdPerfHighlights').innerHTML = `
        <div class="sd-perf-highlight" style="background:var(--sd-green-light);color:var(--sd-dark-green)"><i class="fa-solid fa-trophy"></i> ${sdEsc(sdT('bestPerformer'))}: ${sdEsc(sdProdName(best))}</div>
        <div class="sd-perf-highlight" style="background:var(--sd-orange-light);color:#a5620f"><i class="fa-solid fa-triangle-exclamation"></i> ${sdEsc(sdT('lowPerformer'))}: ${sdEsc(sdProdName(worst))}</div>`;

    table.innerHTML = `<thead><tr><th>${sdEsc(sdT('thProduct'))}</th><th>${sdEsc(sdT('thViews'))}</th>
        <th>${sdEsc(sdT('thOrders'))}</th><th>${sdEsc(sdT('thSold'))}</th><th>${sdEsc(sdT('thConversion'))}</th>
        <th>${sdEsc(sdT('thRevenue'))}</th><th>${sdEsc(sdT('thRating'))}</th><th>${sdEsc(sdT('thRemaining'))}</th></tr></thead>
        <tbody>${rows.map(p => `<tr>
            <td style="font-weight:700">${sdEsc(sdProdName(p))}</td><td>${p.views_count}</td><td>${p.order_count}</td>
            <td>${p.sold_quantity}</td><td>${p.conversion_rate}%</td><td>${sdMoney(p.revenue)}</td>
            <td>${p.rating_count > 0 ? (Number(p.rating_avg).toFixed(1) + ' ★ (' + p.rating_count + ')') : '-'}</td><td>${p.stock}</td>
        </tr>`).join('')}</tbody>`;
}

/* ---------------------------------------------------------------- CUSTOMERS */
async function sdLoadCustomers() {
    const r = await sdApi('customers_list');
    if (!r.success) return;
    sdCache.customers = r.data;
    sdCache.render_customers = sdRenderCustomers;
    sdRenderCustomers();
}
function sdRenderCustomers() {
    const rows = sdCache.customers; if (!rows) return;
    const table = document.getElementById('sdCustomersTable');
    if (!rows.length) { table.innerHTML = sdEmptyState('emptyCustomers', null, 'fa-users'); return; }
    table.innerHTML = `<thead><tr><th>${sdEsc(sdT('thCustomer'))}</th><th>${sdEsc(sdT('thProductsBought'))}</th>
        <th>${sdEsc(sdT('thQty'))}</th><th>${sdEsc(sdT('thTotal'))}</th><th>${sdEsc(sdT('thOrders'))}</th>
        <th>${sdEsc(sdT('thLastPurchase'))}</th><th>${sdEsc(sdT('thLocation'))}</th><th>${sdEsc(sdT('thActions'))}</th></tr></thead>
        <tbody>${rows.map((c, i) => `<tr>
            <td style="font-weight:700">${sdEsc(c.buyer_name || '-')}</td><td>${sdEsc(c.products_bought || '-')}</td>
            <td>${c.total_qty}</td><td>${sdMoney(c.total_amount)}</td><td>${c.order_count}</td>
            <td>${sdEsc(c.last_purchase)}</td><td>${sdEsc(c.delivery_location || '-')}</td>
            <td><button class="sd-btn sd-btn-green" onclick="sdShowContact(${i})"><i class="fa-solid fa-phone"></i> ${sdEsc(sdT('contact'))}</button></td>
        </tr>`).join('')}</tbody>`;
}
function sdShowContact(i) {
    const c = sdCache.customers[i];
    document.getElementById('sdContactContent').innerHTML = `
        <h3><i class="fa-solid fa-address-card"></i> ${sdEsc(sdT('contactTitle'))}</h3>
        <p><strong>${sdEsc(sdT('thCustomer'))}:</strong> ${sdEsc(c.buyer_name || '-')}</p>
        <p><strong>${sdEsc(sdT('contactPhone'))}:</strong> ${sdEsc(c.buyer_mobile_masked || '-')}</p>
        <p><strong>${sdEsc(sdT('thLocation'))}:</strong> ${sdEsc(c.delivery_location || '-')}</p>
        <div class="sd-modal-actions"><button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdContactModal')">${sdEsc(sdT('contactClose'))}</button></div>`;
    sdOpenModal('sdContactModal');
}

/* ---------------------------------------------------------------- REVIEWS */
async function sdLoadReviews() {
    const rating = document.getElementById('sdReviewFilter').value;
    const r = await sdApi('reviews_list', { rating });
    if (!r.success) return;
    sdCache.reviews = r;
    sdCache.render_reviews = () => sdRenderReviews(r);
    sdRenderReviews(r);
}
function sdStarsHtml(n) { return '★'.repeat(n) + '☆'.repeat(5 - n); }
function sdRenderReviews(r) {
    const s = r.summary;
    document.getElementById('sdReviewSummary').innerHTML = `
        <div class="sd-card sd-card-static"><div class="sd-card-icon sd-ic-orange"><i class="fa-solid fa-star"></i></div><div><div class="sd-card-value">${s.average}</div><div class="sd-card-label">${sdEsc(sdT('avgRating'))}</div></div></div>
        <div class="sd-card sd-card-static"><div class="sd-card-icon sd-ic-green"><i class="fa-solid fa-comments"></i></div><div><div class="sd-card-value">${s.total}</div><div class="sd-card-label">${sdEsc(sdT('totalReviews'))}</div></div></div>
        ${[5,4,3,2,1].map(n => `<div class="sd-card sd-card-static"><div class="sd-card-icon sd-ic-orange"><i class="fa-solid fa-star"></i></div><div><div class="sd-card-value">${s.counts[n] || 0}</div><div class="sd-card-label">${n} ★</div></div></div>`).join('')}`;

    const list = document.getElementById('sdReviewsList');
    if (!r.data.length) { list.innerHTML = sdEmptyState('emptyReviews', null, 'fa-star'); return; }
    list.innerHTML = r.data.map(rv => `
        <div class="sd-review-card">
            <div class="sd-review-head">
                <img class="sd-avatar-sm" src="${rv.buyer_avatar ? '../' + rv.buyer_avatar : 'https://ui-avatars.com/api/?background=EAF4EC&color=3F8F5F&name=' + encodeURIComponent(rv.buyer_name||'?')}" alt="">
                <div><div style="font-weight:700">${sdEsc(rv.buyer_name || '-')} ${rv.verified_purchase ? `<span class="sd-badge sd-badge-green">${sdEsc(sdT('verifiedPurchase'))}</span>` : ''}</div>
                <div class="sd-stars">${sdStarsHtml(rv.rating)}</div></div>
                <div style="margin-left:auto;font-size:11px;color:var(--sd-muted)">${sdEsc(rv.created_at)}</div>
            </div>
            <div style="font-size:12px;color:var(--sd-muted);margin-bottom:4px">${sdEsc(rv.product_name)}</div>
            <div>${sdEsc(rv.review_text || '')}</div>
            ${rv.review_images && rv.review_images.length ? `<div class="sd-review-imgs">${rv.review_images.map(im => `<img src="../${sdEsc(im)}" alt="">`).join('')}</div>` : ''}
            ${rv.reply_text ? `<div class="sd-reply-box"><strong>${sdEsc(sdT('yourReply'))}:</strong> ${sdEsc(rv.reply_text)}</div>` :
                `<div style="margin-top:10px"><textarea class="sd-input" rows="2" id="sdReplyBox${rv.id}" placeholder="${sdEsc(sdT('writeReply'))}"></textarea>
                <button class="sd-btn sd-btn-green" style="margin-top:6px" onclick="sdPostReply(${rv.id})"><i class="fa-solid fa-reply"></i> ${sdEsc(sdT('post'))}</button></div>`}
        </div>`).join('');
}
async function sdPostReply(reviewId) {
    const text = document.getElementById('sdReplyBox' + reviewId).value.trim();
    if (!text) return;
    const r = await sdApi('review_reply_save', { review_id: reviewId, reply_text: text }, 'POST');
    sdToast(r.success ? sdT('toastSaved') : sdT('toastError'));
    if (r.success) sdLoadReviews();
}

/* ---------------------------------------------------------------- EARNINGS */
async function sdLoadEarnings() {
    const r = await sdApi('earnings_summary');
    if (!r.success) return;
    sdCache.earnings = r.data;
    sdCache.render_earnings = sdRenderEarnings;
    sdRenderEarnings();
}
function sdRenderEarnings() {
    const d = sdCache.earnings; if (!d) return;
    document.getElementById('sdEarningsCards').innerHTML = `
        <div class="sd-card sd-card-static"><div class="sd-card-icon sd-ic-green"><i class="fa-solid fa-wallet"></i></div><div><div class="sd-card-value">${sdMoney(d.available_balance)}</div><div class="sd-card-label">${sdEsc(sdT('availableBalance'))}</div></div></div>
        <div class="sd-card sd-card-static"><div class="sd-card-icon sd-ic-orange"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="sd-card-value">${sdMoney(d.pending_balance)}</div><div class="sd-card-label">${sdEsc(sdT('pendingBalance'))}</div></div></div>
        <div class="sd-card sd-card-static"><div class="sd-card-icon sd-ic-orange"><i class="fa-solid fa-clock-rotate-left"></i></div><div><div class="sd-card-value">${sdMoney(d.processing_amount)}</div><div class="sd-card-label">${sdEsc(sdT('processingAmount'))}</div></div></div>
        <div class="sd-card sd-card-static"><div class="sd-card-icon sd-ic-green"><i class="fa-solid fa-sack-dollar"></i></div><div><div class="sd-card-value">${sdMoney(d.total_earnings)}</div><div class="sd-card-label">${sdEsc(sdT('totalEarnings'))}</div></div></div>
        <div class="sd-card sd-card-static"><div class="sd-card-icon sd-ic-danger"><i class="fa-solid fa-hand-holding-dollar"></i></div><div><div class="sd-card-value">${sdMoney(d.total_platform_charges)}</div><div class="sd-card-label">${sdEsc(sdT('totalCharges'))}</div></div></div>
        <div class="sd-card sd-card-static"><div class="sd-card-icon sd-ic-green"><i class="fa-solid fa-circle-check"></i></div><div><div class="sd-card-value">${sdMoney(d.total_paid)}</div><div class="sd-card-label">${sdEsc(sdT('totalPaid'))}</div></div></div>
        <div class="sd-card sd-card-static"><div class="sd-card-icon sd-ic-orange"><i class="fa-solid fa-calendar-check"></i></div><div><div class="sd-card-value">${sdEsc(d.next_payout_date)}</div><div class="sd-card-label">${sdEsc(sdT('nextPayout'))}</div></div></div>`;

    document.getElementById('sdPayoutDetails').innerHTML = (d.bank_account_number || d.upi_id) ? `
        <p><strong>${sdEsc(sdT('businessName'))}:</strong> ${sdEsc(d.business_name || '-')}</p>
        <p><strong>${sdEsc(sdT('bankAccNo'))}:</strong> ${d.bank_account_number ? '••••' + sdEsc(String(d.bank_account_number).slice(-4)) : '-'}</p>
        <p><strong>${sdEsc(sdT('upiId'))}:</strong> ${sdEsc(d.upi_id || '-')}</p>` :
        `<p style="color:var(--sd-muted)">${sdEsc(sdT('noBankDetails'))}</p>`;

    const sigUploaded = d.signature_status === 'uploaded';
    const stampUploaded = d.stamp_status === 'uploaded';
    document.getElementById('sdSignatureStatus').innerHTML = `
        <p><strong>${sdEsc(sdT('lblDigitalSignature'))}:</strong> <span class="sd-badge ${sigUploaded ? 'sd-badge-green' : 'sd-badge-orange'}">${sigUploaded ? sdEsc(sdT('uploaded')) : sdEsc(sdT('missing'))}</span></p>
        <p><strong>${sdEsc(sdT('lblOfficialStamp'))}:</strong> <span class="sd-badge ${stampUploaded ? 'sd-badge-green' : 'sd-badge-orange'}">${stampUploaded ? sdEsc(sdT('uploaded')) : sdEsc(sdT('missing'))}</span></p>
        ${d.authorized_signatory_name ? `<p><strong>${sdEsc(sdT('lblAuthSignatoryName'))}:</strong> ${sdEsc(d.authorized_signatory_name)}</p>` : ''}`;

    const g = d.gst || {};
    const gstStatusLabels = { registered: 'Registered', composition: 'Composition Scheme', unregistered: 'Unregistered', not_applicable: 'Not Applicable' };
    const gstVerified = g.verified_status === 'verified';
    const gstReqPending = g.request_status === 'pending';
    const gstBtn = document.getElementById('sdGstVerifyBtn');
    gstBtn.style.display = (g.gstin && !gstVerified) ? '' : 'none';
    if (gstReqPending) {
        gstBtn.disabled = true;
        gstBtn.querySelector('span').textContent = sdT('gstReviewPending') || 'Verification pending review';
    } else {
        gstBtn.disabled = false;
        gstBtn.querySelector('span').textContent = sdT('verifyGstin') || 'Verify GSTIN';
    }
    document.getElementById('sdGstDetails').innerHTML = (g.gstin || g.gst_status) ? `
        <p><strong>${sdEsc(sdT('lblGstStatus'))}:</strong> ${sdEsc(gstStatusLabels[g.gst_status] || 'Not Set')}
            <span class="sd-badge ${gstVerified ? 'sd-badge-green' : (gstReqPending ? 'sd-badge-orange' : 'sd-badge-orange')}">${gstVerified ? sdEsc(sdT('verified')) : (gstReqPending ? (sdT('gstReviewPending') || 'Pending Review') : sdEsc(sdT('notVerified')))}</span></p>
        ${g.gstin ? `<p><strong>${sdEsc(sdT('lblGstin'))}:</strong> ${sdEsc(g.gstin)}</p>` : ''}
        ${g.pan ? `<p><strong>${sdEsc(sdT('lblPan'))}:</strong> ${sdEsc(g.pan)}</p>` : ''}
        ${g.business_type ? `<p><strong>${sdEsc(sdT('lblBusinessType'))}:</strong> ${sdEsc(g.business_type)}</p>` : ''}
        ${g.state ? `<p><strong>${sdEsc(sdT('lblState'))}:</strong> ${sdEsc(g.state)} (${sdEsc(g.state_code||'')})</p>` : ''}` :
        `<p style="color:var(--sd-muted)">${sdEsc(sdT('noGstDetails'))}</p>`;

    const table = document.getElementById('sdPayoutsTable');
    if (!d.payouts.length) { table.innerHTML = sdEmptyState('emptyPayouts', null, 'fa-clock-rotate-left'); return; }
    const stMap = { pending: 'payoutPending', processing: 'payoutProcessing', completed: 'payoutCompleted', rejected: 'payoutRejected' };
    const badgeMap = { pending: 'sd-badge-orange', processing: 'sd-badge-green', completed: 'sd-badge-green', rejected: 'sd-badge-danger' };
    table.innerHTML = `<thead><tr><th>${sdEsc(sdT('thAmount'))}</th><th>${sdEsc(sdT('thMethod'))}</th><th>${sdEsc(sdT('thPayoutStatus'))}</th><th>${sdEsc(sdT('thRequested'))}</th></tr></thead>
        <tbody>${d.payouts.map(p => `<tr><td>${sdMoney(p.amount)}</td><td>${sdEsc((p.method||'').toUpperCase())}</td>
            <td><span class="sd-badge ${badgeMap[p.status]||'sd-badge-grey'}">${sdEsc(sdT(stMap[p.status]||p.status))}</span></td>
            <td>${sdEsc(p.requested_at)}</td></tr>`).join('')}</tbody>`;
}

/* ---------------------------------------------------------------- WITHDRAW / PAYOUT DETAILS */
function sdOpenWithdrawModal() {
    if (!sdCache.earnings) { sdShowSection('earnings'); setTimeout(sdOpenWithdrawModal, 400); return; }
    const d = sdCache.earnings;
    document.getElementById('sdWithdrawAvailable').textContent = sdMoney(d.available_balance);
    document.getElementById('sdWithdrawAmount').value = '';
    document.getElementById('sdWithdrawMethod').value = (d.bank_account_number ? 'bank' : (d.upi_id ? 'upi' : 'bank'));
    sdOpenModal('sdWithdrawModal');
}
async function sdSubmitWithdraw() {
    const amount = parseFloat(document.getElementById('sdWithdrawAmount').value);
    const method = document.getElementById('sdWithdrawMethod').value;
    if (!amount || amount <= 0) { sdToast(sdT('toastError')); return; }
    const btn = document.getElementById('sdWithdrawSubmitBtn');
    btn.disabled = true;
    const r = await sdApi('payout_request', { amount, method }, 'POST');
    btn.disabled = false;
    if (r.success) {
        sdCloseModal('sdWithdrawModal');
        sdToast(sdT('toastWithdrawSubmitted'));
        sdLoadEarnings();
        sdLoadSummary();
    } else {
        sdToast(r.error || sdT('toastError'));
    }
}
function sdOpenPayoutDetailsModal() {
    if (!sdCache.earnings) { sdShowSection('earnings'); setTimeout(sdOpenPayoutDetailsModal, 400); return; }
    const d = sdCache.earnings;
    document.getElementById('sdPdBusinessName').value = d.business_name || '';
    document.getElementById('sdPdBankAccName').value = d.bank_account_name || '';
    document.getElementById('sdPdBankAccNo').value = d.bank_account_number || '';
    document.getElementById('sdPdBankIfsc').value = d.bank_ifsc || '';
    document.getElementById('sdPdUpiId').value = d.upi_id || '';
    sdOpenModal('sdPayoutDetailsModal');
}
async function sdSubmitPayoutDetails() {
    const params = {
        business_name: document.getElementById('sdPdBusinessName').value.trim(),
        bank_account_name: document.getElementById('sdPdBankAccName').value.trim(),
        bank_account_number: document.getElementById('sdPdBankAccNo').value.trim(),
        bank_ifsc: document.getElementById('sdPdBankIfsc').value.trim(),
        upi_id: document.getElementById('sdPdUpiId').value.trim(),
    };
    const btn = document.getElementById('sdPdSaveBtn');
    btn.disabled = true;
    const r = await sdApi('profile_save', params, 'POST');
    btn.disabled = false;
    if (r.success) {
        sdCloseModal('sdPayoutDetailsModal');
        sdToast(sdT('toastDetailsSaved'));
        sdLoadEarnings();
    } else {
        sdToast(r.error || sdT('toastError'));
    }
}

function sdOpenSignatureModal() {
    if (!sdCache.earnings) { sdShowSection('earnings'); setTimeout(sdOpenSignatureModal, 400); return; }
    const d = sdCache.earnings;
    document.getElementById('sdSigFile').value = '';
    document.getElementById('sdStampFile').value = '';
    document.getElementById('sdSigName').value = d.authorized_signatory_name || '';
    document.getElementById('sdSigDesignation').value = d.signatory_designation || '';
    sdOpenModal('sdSignatureModal');
}
async function sdSubmitSignature() {
    const sigFile = document.getElementById('sdSigFile').files[0];
    const stampFile = document.getElementById('sdStampFile').files[0];
    const params = {
        authorized_signatory_name: document.getElementById('sdSigName').value.trim(),
        signatory_designation: document.getElementById('sdSigDesignation').value.trim(),
    };
    if (sigFile) params.signature = sigFile;
    if (stampFile) params.stamp = stampFile;

    const btn = document.getElementById('sdSigSaveBtn');
    btn.disabled = true;
    const r = await sdApi('signature_save', params, 'POST');
    btn.disabled = false;
    if (r.success) {
        sdCloseModal('sdSignatureModal');
        sdToast(sdT('toastDetailsSaved'));
        sdLoadEarnings();
    } else {
        sdToast(r.error || sdT('toastError'));
    }
}

/* ---------------------------------------------------------------- GST DETAILS */
function sdGstSyncStateCode() {
    const sel = document.getElementById('sdGstState');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('sdGstStateCode').value = opt ? (opt.getAttribute('data-code') || '') : '';
}
function sdToggleGstinRequired() {
    // purely visual cue; actual enforcement happens server-side too
    const required = document.getElementById('sdGstStatus').value === 'registered';
    document.getElementById('sdGstin').placeholder = required ? '27XXXXXXXXXXXXZ (required)' : '27XXXXXXXXXXXXZ';
}
function sdOpenGstModal() {
    if (!sdCache.earnings) { sdShowSection('earnings'); setTimeout(sdOpenGstModal, 400); return; }
    const g = sdCache.earnings.gst || {};
    document.getElementById('sdGstErr').style.display = 'none';
    document.getElementById('sdGstLegalName').value = g.legal_business_name || '';
    document.getElementById('sdGstStatus').value = g.gst_status || 'not_applicable';
    document.getElementById('sdGstin').value = g.gstin || '';
    document.getElementById('sdGstPan').value = g.pan || '';
    document.getElementById('sdGstBusinessType').value = g.business_type || '';
    document.getElementById('sdGstState').value = g.state || '';
    document.getElementById('sdGstStateCode').value = g.state_code || '';
    document.getElementById('sdGstCity').value = g.city || '';
    document.getElementById('sdGstPincode').value = g.pincode || '';
    document.getElementById('sdGstAddress').value = g.registered_address || '';
    sdToggleGstinRequired();
    sdOpenModal('sdGstModal');
}
async function sdSubmitGstDetails() {
    const errEl = document.getElementById('sdGstErr');
    errEl.style.display = 'none';
    const gstStatus = document.getElementById('sdGstStatus').value;
    const gstin = document.getElementById('sdGstin').value.trim().toUpperCase();
    if (gstStatus === 'registered' && !gstin) {
        errEl.textContent = sdT('gstinRequiredMsg') || 'GSTIN is required when GST Registration Status is "Registered".';
        errEl.style.display = 'block';
        return;
    }
    if (gstin && gstin.length !== 15) {
        errEl.textContent = sdT('invalidGstinMsg') || 'Invalid GSTIN. Please enter a valid 15-character GSTIN.';
        errEl.style.display = 'block';
        return;
    }
    const params = {
        legal_business_name: document.getElementById('sdGstLegalName').value.trim(),
        gst_status: gstStatus,
        gstin: gstin,
        pan: document.getElementById('sdGstPan').value.trim().toUpperCase(),
        business_type: document.getElementById('sdGstBusinessType').value.trim(),
        state: document.getElementById('sdGstState').value,
        state_code: document.getElementById('sdGstStateCode').value,
        city: document.getElementById('sdGstCity').value.trim(),
        pincode: document.getElementById('sdGstPincode').value.trim(),
        registered_address: document.getElementById('sdGstAddress').value.trim(),
    };
    const btn = document.getElementById('sdGstSaveBtn');
    btn.disabled = true;
    const r = await sdApi('gst_save', params, 'POST');
    btn.disabled = false;
    if (r.success) {
        sdCloseModal('sdGstModal');
        sdToast(sdT('toastDetailsSaved'));
        sdLoadEarnings();
    } else if (r.field) {
        errEl.textContent = r.error || sdT('toastError');
        errEl.style.display = 'block';
    } else {
        sdToast(r.error || sdT('toastError'));
    }
}
async function sdVerifyGstin() {
    // Submits a real GST verification request (seller's own exact
    // account id — see seller_api.php case 'gst_verify_request') so it
    // actually reaches Admin's GST Verification Requests queue instead
    // of only showing a toast.
    const btn = document.getElementById('sdGstVerifyBtn');
    if (btn) btn.disabled = true;
    const r = await sdApi('gst_verify_request', {}, 'POST');
    if (btn) btn.disabled = false;
    if (r.success) {
        sdToast(sdT('toastGstVerifyRequested') || 'Verification requested. An admin will review your GSTIN shortly.');
        sdLoadEarnings();
    } else {
        sdToast(r.error || sdT('toastError'));
    }
}

/* ---------------------------------------------------------------- MY ACCOUNT */
async function sdSaveAccountDetails() {
    const name = document.getElementById('sdAccName').value.trim();
    const mobile = document.getElementById('sdAccMobile').value.trim();
    const email = document.getElementById('sdAccEmail').value.trim();
    const address = document.getElementById('sdAccAddress').value.trim();
    if (!name) { sdToast(sdT('toastError')); return; }

    const btn = document.getElementById('sdAccSaveBtn');
    btn.disabled = true;
    try {
        const form = new FormData();
        form.append('name', name);
        form.append('mobile', mobile);
        form.append('email', email);
        form.append('address', address);
        const res = await fetch('../pages/update_profile.php', { method: 'POST', body: form });
        const data = await res.json();
        btn.disabled = false;
        if (data.success) {
            sdToast(sdT('toastDetailsSaved'));
            setTimeout(() => location.reload(), 700); // keeps session/greeting/name in sync everywhere
        } else {
            sdToast(data.message || sdT('toastError'));
        }
    } catch (e) {
        btn.disabled = false;
        sdToast(sdT('toastError'));
    }
}
async function sdChangePassword() {
    const curPwd = document.getElementById('sdAccCurPwd').value;
    const newPwd = document.getElementById('sdAccNewPwd').value;
    const confirmPwd = document.getElementById('sdAccConfirmPwd').value;
    if (!newPwd && !confirmPwd) { sdToast(sdT('toastError')); return; }
    if (newPwd !== confirmPwd) { sdToast(sdT('pwdMismatch')); return; }
    if (newPwd.length < 6) { sdToast(sdT('pwdShort')); return; }

    const btn = document.getElementById('sdAccPwdBtn');
    btn.disabled = true;
    try {
        const form = new FormData();
        form.append('name', document.getElementById('sdAccName').value.trim());
        form.append('current_password', curPwd);
        form.append('new_password', newPwd);
        form.append('confirm_password', confirmPwd);
        const res = await fetch('../pages/update_profile.php', { method: 'POST', body: form });
        const data = await res.json();
        btn.disabled = false;
        if (data.success) {
            sdToast(sdT('toastPasswordUpdated'));
            document.getElementById('sdAccCurPwd').value = '';
            document.getElementById('sdAccNewPwd').value = '';
            document.getElementById('sdAccConfirmPwd').value = '';
        } else {
            sdToast(data.message || sdT('toastError'));
        }
    } catch (e) {
        btn.disabled = false;
        sdToast(sdT('toastError'));
    }
}

/* ---------------------------------------------------------------- NOTIFICATIONS */
const SD_NOTIF_ICON = { new_order:'fa-cart-shopping', low_stock:'fa-triangle-exclamation', out_of_stock:'fa-ban',
    new_review:'fa-star', payment_received:'fa-coins', order_cancelled:'fa-rotate-left',
    product_approved:'fa-circle-check', product_rejected:'fa-circle-xmark' };
async function sdPollNotifications() {
    const r = await sdApi('notifications_list');
    if (!r.success) return;
    sdCache.notifications = r;
    const dot = document.getElementById('sdBellDot');
    const navBadge = document.getElementById('sdNavNotifBadge');
    if (r.unread > 0) { dot.classList.add('show'); navBadge.textContent = r.unread; navBadge.classList.add('show'); }
    else { dot.classList.remove('show'); navBadge.classList.remove('show'); }
    sdRenderNotifPanel();
    if (document.getElementById('sec-notifications').classList.contains('active')) sdRenderNotifPage();
}
function sdRenderNotifPanel() {
    const r = sdCache.notifications; if (!r) return;
    const panel = document.getElementById('sdNotifPanel');
    if (!r.data.length) { panel.innerHTML = sdEmptyState('emptyNotifs', null, 'fa-bell'); return; }
    panel.innerHTML = r.data.slice(0, 12).map(n => `
        <div class="sd-notif-item ${n.is_read ? '' : 'unread'}" onclick="sdMarkOneRead(${n.id})">
            <div class="sd-notif-title"><i class="fa-solid ${SD_NOTIF_ICON[n.type]||'fa-bell'}"></i> ${sdEsc(n.title)}</div>
            <div>${sdEsc(n.message)}</div><div class="sd-notif-meta">${sdEsc(n.created_at)}</div>
        </div>`).join('');
}
function sdToggleNotifPanel() { document.getElementById('sdNotifPanel').classList.toggle('open'); }
document.addEventListener('click', (e) => {
    const panel = document.getElementById('sdNotifPanel'), bell = document.getElementById('sdBellBtn');
    if (panel && !panel.contains(e.target) && !bell.contains(e.target)) panel.classList.remove('open');
});
function sdLoadNotificationsPage() { sdRenderNotifPage(); }
function sdRenderNotifPage() {
    const r = sdCache.notifications; if (!r) return;
    const list = document.getElementById('sdNotifList');
    if (!r.data.length) { list.innerHTML = sdEmptyState('emptyNotifs', null, 'fa-bell'); return; }
    list.innerHTML = r.data.map(n => `
        <div class="sd-panel" style="display:flex;gap:14px;align-items:flex-start;${n.is_read ? '' : 'border-left:4px solid var(--sd-green)'}" onclick="sdMarkOneRead(${n.id})">
            <div class="sd-card-icon sd-ic-green" style="flex-shrink:0"><i class="fa-solid ${SD_NOTIF_ICON[n.type]||'fa-bell'}"></i></div>
            <div><div style="font-weight:800">${sdEsc(n.title)}</div><div style="font-size:13px;margin:4px 0">${sdEsc(n.message)}</div><div style="font-size:11px;color:var(--sd-muted)">${sdEsc(n.created_at)}</div></div>
        </div>`).join('');
}
async function sdMarkOneRead(id) { await sdApi('notification_mark_read', { id }, 'POST'); sdPollNotifications(); }
async function sdMarkAllRead() { await sdApi('notification_mark_read', { id: 'all' }, 'POST'); sdPollNotifications(); }
