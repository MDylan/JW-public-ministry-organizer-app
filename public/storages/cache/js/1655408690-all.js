;var mainHeader = document.querySelector('.main-header');
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
        console.log('set dark');
        // document.cookie = "currentTheme=dark";
        setCookie("currentTheme", 'dark', 180);
    } else {
        if (document.body.classList.contains('dark-mode')) {
            document.body.classList.remove("dark-mode");
        }
        if (mainHeader.classList.contains('navbar-dark')) {
            mainHeader.classList.add('navbar-light');
            mainHeader.classList.add('navbar-white');
            mainHeader.classList.remove('navbar-dark');
        }
        console.log('set light');
        // document.cookie = "currentTheme=light";
        setCookie("currentTheme", 'light', 180);
    }
}

function setCookie(cname, cvalue, exdays) {
    const d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    let expires = "expires="+d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
  }

$(function () {
    $('[data-toggle="tooltip"]').tooltip()
})

$(".content-wrapper").click(function() {
    if($("#control_sidebar").css( "display" ) == "block") {
        $("#control_sidebar").ControlSidebar('collapse');
    }
});

function showChild(t, cl) {
    $(t).children("."+cl).show();
    $(t).addClass('mt-1');
}

function showFullChild(t, cl) {
    $(t).children("."+cl).removeClass('overflow-hidden');
    $(t).children("."+cl).removeClass('crop_child');
};$(document).ready(function() {
    window.addEventListener('hide-modal', event => {
        id = event.detail.id;
        $('#' + id).modal('hide');
        if(event.detail.message) {
            toastr.success(event.detail.message, event.detail.savedMessage);
        }
    });
    window.addEventListener('show-modal', event => {
        id = event.detail.id;
        $('#'+id).modal('show');
        $('#'+id).one('hide.bs.modal', function (e) {
            if(event.detail.livewire) {
                Livewire.emitTo(event.detail.livewire, 'hideModal', event.detail.parameters_back);
            }
        });

        $('#'+id).one('hidden.bs.modal', function (e) {
            if(event.detail.livewire) {
                Livewire.emitTo(event.detail.livewire, 'hiddenModal', event.detail.parameters_back);
            }
        });
    });
});