<?php

return array (
  'app_name' => 'Der Name der Seite (er erscheint auf der Mail des Absenders)',
  'app_url' => 'Webadresse der Seite (URL)',
  'failed_jobs_retry' => 'Fehlerhafte Aufgaben erneut ausführen',
  'homepage_message' => 'Inhalt der Nachricht zum Eröffnungsseite',
  'languages' => 
  array (
    'confirmDelete' => 
    array (
      'message' => 'Danach wird die Sprache für niemanden mehr verfügbar sein.',
      'question' => 'Bist du dir sicher, dass du diese Sprache löschen willst?',
      'success' => 'Du hast die Sprache erfolgreich gelöscht.',
    ),
    'country_code' => 'Ländercode',
    'country_name' => 'Sprache (Diese wird in der Dropdown-Liste angezeigt.)',
    'default' => 'Standardsprache',
    'defaultSet' => 
    array (
      'error' => 'Diese Sprache existiert nicht in der Liste der Sprachen!',
      'success' => 'Die neue Standardsprache ist eingestellt',
    ),
    'empty' => 'Du hast noch keine Sprache hinzugefügt.',
    'lang_help' => 'Die Sprachauswahl wird angezeigt, wenn mindestens 2 Sprachen verfügbar sind.<br/> Um eine bestehende Sprache zu ändern, gebe erneut den Ländercode und den zu ändernden Text ein. Du kannst eine "Übersetzer"-Berechtigung für Benutzer auswählen, denen du erlauben möchtest, online zu übersetzen. <hr> Bevor du eine neue Sprache zur Verfügung stellst, vergewissere dich bitte, dass die Sprachdateien im Ordner "/resources/lang" vorhanden sind. <br/> Wenn etwas in der Sprache nicht übersetzt ist, ist die Seite zwar noch benutzbar, aber es erscheint kein sinnvoller Text anstelle des Inhalts.<br/> Weitere Informationen findest du in der <a class="alert-link" href="https://laravel.com/docs/8.x/localization" target="_blank">lavarel Dokumentation</a>',
    'start_translation' => 'Übersetzung beginnen',
    'success' => 'Die Sprache wurde hinzugefügt',
    'title' => 'Verfügbare Sprachen',
    'translate' => 'Übersetzung',
    'translator_help' => 'Ein Administrator kann eine neue Sprache hinzufügen und die Sichtbarkeit der Sprache einstellen. Wenn du mit dem Übersetzen fertig bist, kannst du dies dem Administrator melden.',
    'visibility' => 
    array (
      'admin' => 'Nur für den Admin sichtbar',
      'show' => 'Für alle sichtbar',
    ),
    'visibility_changed' => 'Die Sichtbarkeit der Sprache hat sich verändert.',
  ),
  'mail' => 'Brief abschicken',
  'mail_encryption' => 'Für die Korrespondenz verwendete Verschlüsselungsmethode',
  'mail_from_address' => 'E-Mail Adresse des Absenders',
  'mail_host' => 'Adresse des Mailservers',
  'mail_info' => 'Die Website sendet unzählige von Benachrichtigungen an den Benutzer, daher ist es sehr wichtig, die Mailingliste richtig einzurichten. Du kannst die Einstellungen testen, ohne zu speichern, indem du auf die Schaltfläche "Verbindung testen" klickst.',
  'mail_mailer' => 'Wie versende ich Post?',
  'mail_password' => 'Passwort für die Korrespondenz',
  'mail_port' => 'Port (Beispiel: 25 / 465 / 587)',
  'mail_test' => 'Testen der Verbindung',
  'mail_test_error' => 'Fehler beim Senden!',
  'mail_test_on_progress' => 'Bitte warte, ich versuche dir einen Brief zu schicken...',
  'mail_test_success' => 'Erfolgreicher E-Mail-Versand!',
  'mail_username' => 'Für die Korrespondenz verwendeter Benutzername',
  'main' => 
  array (
    'info' => 'Bitte beachte, dass Änderungen, die du hier vornimmst, einen _großen_ Einfluss auf die Nutzung der gesamten Website haben können.',
    'title' => 'Standardeinstellungen für die Website',
  ),
  'no_encryption' => 'Keine Verschlüsselung',
  'others' => 
  array (
    'claim_group_creator' => 'Auf der Seite Gruppen kannst du Rechte zur Erstellung von Gruppen beantragen.',
    'debugbar' => 'Debugbar einschalten (Nur zu Test-/Fehlersuchzwecken einschalten! Ansonsten Sicherheitsrisiko!)',
    'gdpr' => 'Aktivieren Sie GDPR (aktiviere es, wenn du die Website innerhalb der EU betreibtest). Benutzer können ihre persönlichen Daten in einer .json-Datei speichern.',
    'maintenance' => 'Wartungsmodus (Nur Administratoren können sich anmelden, sonst niemand.)',
    'registration' => 'Möglichkeit zur Registrierung. 
(Wenn du diese Funktion deaktivieren, kannst nur angemeldete Benutzer - z. B. Gruppenleiter - neue Benutzer anlegen).',
    'show_homepage_alert' => 'Nachricht auf der Startseite anzeigen',
    'terms_checkbox' => 'Nutzungsbedingungen bei der Registrierung (wenn du diese Option deaktivierst, musst du keine Nutzungsbedingungen akzeptierst)',
    'title' => 'Sonstige Einsteillungen',
    'use_https' => 'https-Verschlüsselung aktivieren (Achtung! Schalte sie nur ein, wenn dein Hosting-Anbieter die https-Verbindung aktiviert hat. Wenn du sie nicht richtig aktivierst, wird die Seite nicht geladen!)',
    'use_recaptcha' => 'Verwende reCaptcha (Spam-Schutz).
Wenn du es aktivierst, kannst du den Schutz der Webseite vor Robotern erheblich verstärken. Google reCaptcha wird bei der Anmeldung und Registrierung auf der Website verwendet.',
  ),
  'others_saved' => 'Die Einstellungen wurden erfolgreich gespeichert.',
  'recaptcha' => 
  array (
    'info' => 'Wenn du diese Funktion aktivierst, kannst du den Schutz deiner Website vor Robotern erheblich verbessern. Google recaptchat wird bei der Anmeldung und Registrierung auf der Website verwendet.',
  ),
  'run' => 
  array (
    'optimize' => 'Ausführen eines Befehls',
    'success' => 'Der Auftrag wurde ausgeführt!',
    'title' => 'Artisan-Befehle ausführen',
  ),
  'schedule_error' => 'FEHLER! Die geplante Aufgabe läuft schon sehr lange (oder gar nicht), was zu Betriebsstörungen führen kann.',
  'schedule_info' => 'Geplante Aufgaben stellen sicher, dass die Website ordnungsgemäß funktioniert (z. B. werden E-Mail-Benachrichtigungen verschickt, Benutzerdaten aktualisiert usw. Stelle sicher, dass dein Hosting-Provider so konfiguriert ist, dass diese Aufgaben jede Minute ausgeführt werden (cron). Mehr dazu <a href="https://laravel.com/docs/8.x/scheduling#running-the-scheduler" target="_blank">in der Dokumentation.</a>',
  'software_url' => 'Die Software-Website',
  'status' => 
  array (
    'failed_jobs' => 'Falsche Aufgaben',
    'last_schedule_run' => 'Letzter Lauf der geplanten Aufgaben',
    'php_version' => 'PHP-Version',
    'software_version' => 'Software-Version',
    'title' => 'Status des Systems',
    'waiting_jobs' => 'Aufgaben auf Warteliste',
  ),
  'there_are_failed_jobs' => 'Bei der Ausführung einer oder mehrerer geplanter Aufgaben ist ein Fehler aufgetreten. Du wurdest per E-Mail benachrichtigt. Versuche sie erneut auszuführen. Wenn du immer noch einen Fehler erhalten, melde den Fehler bitte dem Programmentwickler und sende ihm den Inhalt der E-Mail.',
  'timezone' => 'Zeitzone',
);
