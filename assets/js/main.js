(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {

    /* ---- Demo language switcher wiring (replace with real header JS) ---- */
    var langSwitch = document.getElementById("langSwitch");
    var langTrigger = document.getElementById("langTrigger");
    var langMenu = document.getElementById("langMenu");

    if (langSwitch && langTrigger && langMenu) {
      langTrigger.addEventListener("click", function () {
        var isOpen = langSwitch.classList.toggle("open");
        langTrigger.setAttribute("aria-expanded", isOpen ? "true" : "false");
      });
      document.addEventListener("click", function (e) {
        if (!langSwitch.contains(e.target)) {
          langSwitch.classList.remove("open");
          langTrigger.setAttribute("aria-expanded", "false");
        }
      });
      langMenu.querySelectorAll("button[data-lang]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var lang = btn.getAttribute("data-lang");
          window.AgriCartI18n.setLanguage(lang);
          langSwitch.classList.remove("open");
          langTrigger.setAttribute("aria-expanded", "false");
          document.dispatchEvent(new CustomEvent("agricart:languagechange", { detail: { lang: lang } }));
        });
      });
    }

    /* ---- FAQ accordion ---- */
    document.querySelectorAll(".acc-item").forEach(function (item) {
      var trigger = item.querySelector(".acc-trigger");
      var panel = item.querySelector(".acc-panel");
      trigger.addEventListener("click", function () {
        var isOpen = item.classList.contains("open");
        document.querySelectorAll(".acc-item.open").forEach(function (other) {
          if (other !== item) {
            other.classList.remove("open");
            other.querySelector(".acc-trigger").setAttribute("aria-expanded", "false");
            other.querySelector(".acc-panel").style.maxHeight = null;
          }
        });
        if (isOpen) {
          item.classList.remove("open");
          trigger.setAttribute("aria-expanded", "false");
          panel.style.maxHeight = null;
        } else {
          item.classList.add("open");
          trigger.setAttribute("aria-expanded", "true");
          panel.style.maxHeight = panel.scrollHeight + "px";
        }
      });
    });

    /* ---- Scroll reveal ---- */
    var revealEls = document.querySelectorAll(".reveal");
    if ("IntersectionObserver" in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("in");
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12 });
      revealEls.forEach(function (el) { io.observe(el); });
    } else {
      revealEls.forEach(function (el) { el.classList.add("in"); });
    }

    /* ---- Loader ---- */
    window.addEventListener("load", function () {
      setTimeout(function () {
        var loader = document.getElementById("pageLoader");
        if (loader) loader.classList.add("hide");
      }, 250);
    });
    setTimeout(function () {
      var loader = document.getElementById("pageLoader");
      if (loader) loader.classList.add("hide");
    }, 1800);

  });
})();
