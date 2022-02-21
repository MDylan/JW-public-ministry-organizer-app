var mainHeader = document.querySelector('.main-header');
var currentTheme = localStorage.getItem('theme_settings');

var theme_handler = function (e) {
    current_system_theme = e.matches ? "dark" : "light";
    selected = $("#theme_selector").val();
    if(selected == 'system') {
        switchTheme2(current_system_theme);        
    }
}

if (currentTheme) {
    $("#theme_selector").val(currentTheme);
    if(currentTheme == 'system') {
        checkSystemTheme();
    } else {
        switchTheme2(currentTheme);
    }
} else {
    checkSystemTheme();
}

function checkSystemTheme() {
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        switchTheme2('dark');
    } else {
        switchTheme2('light');
    }
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener("change", theme_handler);
}

$("#theme_selector").on("change", function() {
    var theme = $(this).val();
    localStorage.setItem('theme_settings', theme);
    if(theme !== 'system') {
        switchTheme2(theme);
        // window.matchMedia('(prefers-color-scheme: dark)').removeEventListener("change", theme_handler);
    } else {
        checkSystemTheme();
    }
});

function switchTheme2(e) {
    if (e == 'dark') {
        if (!document.body.classList.contains('dark-mode')) {
            document.body.classList.add("dark-mode");
        }
        if (mainHeader.classList.contains('navbar-light')) {
            mainHeader.classList.add('navbar-dark');
            mainHeader.classList.remove('navbar-light');
            mainHeader.classList.remove('navbar-white');
        }
    } else {
        if (document.body.classList.contains('dark-mode')) {
            document.body.classList.remove("dark-mode");
        }
        if (mainHeader.classList.contains('navbar-dark')) {
            mainHeader.classList.add('navbar-light');
            mainHeader.classList.add('navbar-white');
            mainHeader.classList.remove('navbar-dark');
        }
    }
}

$(function () {
    $('[data-toggle="tooltip"]').tooltip()
})

$(".content-wrapper").click(function() {
    if($("#control_sidebar").css( "display" ) == "block") {
        $("#control_sidebar").ControlSidebar('collapse');
    }
});