document.addEventListener("DOMContentLoaded", function () {
    var menu = document.getElementById("menu-tutor");
    var toggle = document.querySelector(".dashboard-menu-toggle");
    var links = document.querySelectorAll(".sidebar-link");

    if (menu && toggle) {
        toggle.addEventListener("click", function () {
            var open = menu.classList.toggle("is-open");
            toggle.setAttribute("aria-expanded", open ? "true" : "false");
            toggle.setAttribute("aria-label", open ? "Cerrar menú" : "Abrir menú");
        });
    }

    links.forEach(function (link) {
        link.addEventListener("click", function () {
            links.forEach(function (item) {
                item.classList.remove("active");
            });
            link.classList.add("active");
            if (menu && toggle) {
                menu.classList.remove("is-open");
                toggle.setAttribute("aria-expanded", "false");
                toggle.setAttribute("aria-label", "Abrir menú");
            }
        });
    });
});
