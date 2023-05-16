<?php

return array (
  'GroupUserLogout' => 
  array (
    'line_1' => 'Wir informieren dich, dass du die Gruppe :groupName verlassen hast. Du wirst den Kalender der Gruppe nicht mehr sehen, und deine zukünftigen Dienste wurden aus dieser Gruppe gelöscht.',
    'line_2' => 'Dein Austreten wurde von :userName initiiert, wenn du es nicht bist, kannst du dich bei ihm/ihr erkundigen.',
    'subject' => 'Du hast die Gruppe :groupName verlassen',
  ),
  'ParentGroupAttached' => 
  array (
    'line_1' => 'Wir möchten dich darüber informieren, dass eine neue Untergruppe zu der Gruppe :groupName hinzugefügt wurde.',
    'line_2' => 'Ab jetzt werden alle Verkündiger automatisch in die Untergruppe kopiert.',
    'line_3' => 'Name der Untergruppe: :childGroupName',
    'line_4' => 'Die Veränderungen wurden von :userName durchgeführt.',
    'line_5' => 'Wenn du diese Einstellung ändern möchtest, klicke bitte in das Menü "Verkündiger" und trenne die Verbindung.',
    'subject' => 'Wichtig: Neue Hauptgruppe wurde festgelegt',
  ),
  'ParentGroupDetached' => 
  array (
    'line_1' => 'Wir möchten dich darüber informieren, dass deine Gruppe :childgroupName',
    'line_2' => 'Ab jetzt weden Verkündiger nicht mehr automatisch in diese Gruppe verschoben.',
    'line_3' => 'Der aktuelle Name der Hauptgruppe lautet :groupName',
    'line_4' => 'Die Veränderungen wurden von :userName durchgeführt.',
    'line_5' => 'Wenn du diese Einstellung ändern möchtest, gehe bitte in das Menü "Verkündiger" und verknüpfe die beiden Gruppen erneut.',
    'subject' => 'Wichtig: Die Einstellung der Hauptgruppe wurde entfernt',
  ),
  'anonymize' => 
  array (
    'line_1' => 'Du hast dich schon zu lange nicht mehr bei :appName eingeloggt, daher werden wir aus Datenschutzgründen dein Konto und deine persönliche Daten bald löschen. Danach kannst du dich nicht mehr anmelden.',
    'line_2' => 'Wenn du nicht möchtest, dass wir deine Daten löschen, brauchen wir nur einmals bis zum :lastDate einzuloggen oder deine Gruppenbetreuer bitten, deinen Zugang zu verlängern.',
    'subject' => 'WICHTIG: Dein Zugang wird bald gelöscht',
  ),
  'anonymizeAdmin' => 
  array (
    'line_1' => 'Wir möchten dich darüber informieren, dass wir aufgrund der GDPR-Datenschutzgesetze in Kürze das Konto und die persönlichen Daten des/der folgenden - inaktiven - Verkündiger löschen werden. Danach kannst du dich nicht mehr anmelden. Darüber wird man auch eine E-Mail-Benachrichtigung erhalten.',
    'line_2' => 'Dies ist notwendig, weil man sich schon zu lange nicht mehr auf der Webseite waren.',
    'line_3' => 'Wenn du dies verhindern möchtest, gibt es zwei Möglichkeiten, dies zu tun:',
    'line_4' => '1. Der Verkündiger betretet die Seite bis den angegebenen Zeitpunkt.',
    'line_5' => '2. Du beantragst im "Verkündiger" Menü das Behalten der Daten der Verkündiger.',
    'line_6' => 'Gruppe: :group',
    'line_7' => 'Betroffener Verkündiger (mit einer Frist):',
    'subject' => 'WICHTIG: Einige Benutzer in deiner Gruppe werden bald gelöscht.',
  ),
  'deletePersonalData' => 
  array (
    'line_1' => 'Du hast uns darum gebeten, deine persönlichen Daten zu löschen.',
    'line_2' => 'Bitte bestätige deine Absicht, dies zu tun, indem  du aug den unten stehenden Link klickst. Wenn du auf die Schlatfläche klickst, wird der Link automatisch gelöscht.',
    'line_3' => 'Zu deiner Sicherheit ist dieser Link nur 1 Stunde lang gültig. Wenn du die Löschung deiner Daten nicht beantragt hast, klicke nicht auf den Link und ändere sofort dein Passwort auf der Website, denn das hat jemand anderes für dich getan.',
    'subject' => 'Löschen von persönliche Daten',
  ),
  'event' => 
  array (
    'created' => 
    array (
      'line_1' => 'Wir möchten dich darüber informieren, dass :userName in :groupName den folgenden Dienst für dich geplant hat.',
      'line_2' => ':newServiceDate',
      'line_3' => 'Viel Freude im Dienst!',
      'subject' => 'Dein Dienst, geplant für :date (:groupName)',
    ),
    'deleted' => 
    array (
      'line_1' => 'Wir möchten dich darüber informieren, dass dein Dienst in der Gruppe :groupName gelöscht wurde:',
      'line_2' => ':oldServiceDate',
      'line_3' => 'Grund der Stornierung: :reason',
      'line_4' => 'Die Löschung wurde von :userName veranlasst, er/sie kann dir weitere Informationen liefern.',
      'subject' => 'Dein für :date geplanter Dienst wurde storniert. 
(:groupName)',
    ),
    'deleted_to_admin' => 
    array (
      'line_1' => 'Wir möchten dich darüber informieren, dass der folgende Dienst in der :groupName gelöscht wurde:',
      'line_2' => ':oldServiceDate',
      'line_3' => 'Grund der Stornierung: :reason',
      'line_4' => 'Die Löschung wurde von :userName veranlasst, er/sie kann dir weitere Informationen liefern.',
      'subject' => 'Der für :date geplanter Dienst wurde abgesagt! (:groupName)',
    ),
    'deletion_reasons' => 
    array (
      'modified_service_time' => 'An diesem Tag wurde die Uhrzeit des Dienstes geändert.',
      'service_day_deleted' => 'Für diesen Tag können keine weiteren Dienste mehr angesetzt werden, deswegen wurde der Dienst für alle abgesagt.',
      'unknown' => 'Unbekannt.',
      'user_logout' => 'Du hast die Gruppe verlasst, daher wurden deine Dienste nach dem Verlassen der Gruppe gelöscht.',
    ),
    'modified' => 
    array (
      'line_1' => 'Wir möchten dich darüber informieren, dass dein Dienst in :groupName geändert wurde:',
      'line_2' => 'Altes Datum: :oldServiceDate',
      'line_3' => 'Neues Datum: :newServiceDate',
      'line_4' => 'Bitte plane deinen Dienst entsprechend.',
      'line_5' => 'Grund der Änderung: :reason',
      'line_6' => 'Die Änderung wurde von :userName veranlasst, er/sie kann dir weitere Informationen liefern.',
      'subject' => 'Dein für :date geplanter Dienst wurde geändert! (:groupName)',
    ),
    'modify_reasons' => 
    array (
      'modified_service_time' => 'An diesem Tag wurde dein Dienstzeit geändert und dein geplanter Dienst passte nicht in den neuen Dienstzeitraum.',
      'unknown' => 'Unbekannt.',
    ),
    'status_changed' => 
    array (
      0 => 
      array (
        'line_1' => 'Wir möchten dich darüber informieren, dass der folgende Dienst in der Gruppe :groupName zur Zeit geprüft wird. Der Gruppenaufseher wird in Kürze entscheiden, ob die Zeitpunkt richtig ist, und du wirst über die Entscheidung informiert. Bitte nehmen Sie den Dienst nicht als selbstverständlich hin, bis dies geschieht.',
        'line_2' => ':newServiceDate',
        'subject' => 'Dein Dienst wird gerade geprüft :date',
      ),
      1 => 
      array (
        'line_1' => 'Wir möchten dich darüber informieren, dass in der Gruppe :groupName folgende Dienst angenommen wurde. Schönen Dienst! :)',
        'line_2' => ':newServiceDate',
        'subject' => 'Dein geplanter Dienst wurde genehmigt :date !',
      ),
      2 => 
      array (
        'line_1' => 'Wir möchten Sie darüber informieren, dass der folgende Dienst in :groupName nicht angenommen wurde. Bitte suche einen anderen Zeitpunkt für deinen Dienst.',
        'line_2' => ':newServiceDate',
        'subject' => 'Dein geplanter Dienst wurde nicht genehmigt :date',
      ),
    ),
  ),
  'finishRegistration' => 
  array (
    'done' => 
    array (
      'line_1' => 'Willkommen auf der Website. Du kannst dich nun mit deinem Passwort anmelden. Du kannst deine Gruppeneinladungen im Menü Gruppen annehmen oder ablehnen. Weitere Informationen zur Nutzung der Website findest du im Menü bei Hilfe.',
      'subject' => 'Deine Registrierung ist fertig!',
    ),
    'line_1' => 'Wir möchten dich darüber informieren, dass :groupAdmin dich zu :appName eingeladen hat!',
    'line_2' => 'Du hast :day Tag(e) Zeit, um deine Anmeldung abzuschließen, indem du auf den unten stehenden Link klickst, ansonsten wird dein Konto automatisch gelöscht.',
    'line_3' => 'Benutzername: :userMail',
    'line_4' => 'Bei der Abschließung deiner Anmeldung kannst du dein gewünschtes Passwort eingeben.',
    'line_5' => 'Den unten stehenden Link kannst du nur für abschließen deiner Registrierung benutzen.',
    'subject' => 'Du wurdest eingeladen, ein Benutzer von :appName zu werden',
  ),
  'footer' => 'Dies ist eine automatische E-Mail, bitte antworte nicht.',
  'groupCreator' => 
  array (
    'line_1' => 'Wir möchten dich darüber informieren, dass du die Möglichkeit hast, eine neue Gruppe auf der Webseite zu erstellen. Du kannst dies unter dem Menüpunkt "Gruppen" tun.',
    'line_2' => 'Bitte sehe dir die Videos im Hilfsmenü an, um einen Überblick über die Einstellungsoptionen zu erhalten.
Wo es möglich ist, gibt es auch Beschreibungen, die dir bei der Einstellungen helfen.',
    'subject' => 'Du hast das Recht erhalten, eine Gruppe zu erstellen',
  ),
  'groupUserAdded' => 
  array (
    'line_1' => 'Dies ist eine automatische Benachrichtigung, dass :groupAdmin dich eingeladen hat, der Gruppe :groupName beizutreten.',
    'line_2' => 'Sobald du dich angemeldet hast, kannst du die Einladung im Menü "Gruppen" annehmen oder ablehnen.',
    'subject' => 'Einladung zur Gruppe :groupName',
  ),
  'loginData' => 
  array (
    'line_1' => ':groupAdmin hat ein Konto für dich auf der Website erstellt.',
    'line_2' => 'deine Anmeldedaten',
    'line_3' => 'Benutzername: :userMail',
    'line_4' => 'Passwort: :userPassword',
    'line_5' => 'Bitte ändere diesen Passwort auf jeden Fall, nachdem du dich auf der Seite "Mein Profil" angemeldet hast.',
    'subject' => 'deine Zugangsdaten',
  ),
  'messages' => 
  array (
    'line_1' => 'Eine dringende Nachricht von :userName in :groupName wurde auf dem Message Board veröffentlicht.',
    'line_2' => 'Inhalt der Nachricht:',
    'line_3' => 'Du kannst entweder über das Forum oder über die angegebenen Kontaktdaten antworten. Bitte antworte NICHT auf diese E-mail, da sie nicht an die Person weitergeleitet wird.',
    'subject' => 'Dringende Nachricht von :userName',
  ),
  'newEmailSet' => 
  array (
    'line_1' => 'Dies ist eine automatische Benachrichtigung, über den Änderungsbeantragung für :appName. Die neue Adresse lautet :email.',
    'line_2' => 'An diese neue Adresse wurde eine E-Mail mit weiteren Informationen gesandt, damit die Änderung wirksam werden kann.',
    'line_3' => 'Wenn du keine Änderung deiner E-Mail-Adresse beantragt hast, melde dich bitte auf der Website an und ändere deinen Passwort auf der Seite "Mein Profil".',
    'subject' => 'WICHTIG: Deine E-mail Adresse geändert',
  ),
  'newadmin' => 
  array (
    'line_1' => 'Dies ist eine automatische Benachrichtigung, dass :newAdmin zum Administrator deiner Website ernannt wurde.',
    'line_2' => 'Wer ernannt hat: :adminBy',
    'line_3' => 'Wenn dies ein Fehler ist, entziehe ihm bitte so schnell wie möglich seine Administratorrechte!',
    'subject' => 'Neuer Verwalter ernannt',
  ),
  'profileChanged' => 
  array (
    'line_1' => 'Wir möchten dich darüber informieren, dass :userName deinen Namen oder dein Telefonnummer auf unserer Webseite geändert hat.',
    'line_2' => 'Alte Daten:',
    'line_3' => 'Neue Daten:',
    'line_4' => 'Wenn du feststellst, dass deine neuen Angaben nicht korrekt sind, kannst du nach dem Einloggen im Menü "Mein Profil" korrigieren.',
    'subject' => 'Wir haben deine persönliche Daten geändert',
  ),
  'registerFail' => 'Wenn du diese Registrierung nicht beantragt hast, wird diese Registrierung nach 48 Stunden automatisch gelöscht.',
  'registerWelcome' => 'Deine Registrierung auf :url ist abgeschlossen. Bitte aktiviere deine Registrierung, indem du auf den unten stehenden Link klickst.',
  'signature' => 'Mit freundlichen Grüßen von den Erstellern der Helpdesk-Organisationsseite',
  'testmail' => 
  array (
    'line_1' => 'Dies ist eine Test-Benachrichtigung. Wenn du sie erhaltest, bedeutet, dass deine E-Mail-Einstellungen in Ordnung sind.',
    'line_2' => 'Vielen Dank, dass du diesen App nutzst. :)',
    'subject' => 'Test-Benachrichtigung',
  ),
  'unsubscribe' => 'Wenn du solche E-Mails in der Zukunft nicht mehr erhalten möchtest, kannst du dich im Menü "Mein Profil" abmelden.',
  'userProfileRenewal' => 
  array (
    'line_1' => 'Wir möchten dich darüber informieren, dass :adminName darum gebeten hat, deine Daten weiterhin auf :appName zu speichern, auch wenn du die Webseite schon lange nicht nutzst. Auf diese Weise wird dein Konto nicht gelöscht und deine Daten bleiben erhalten.',
    'line_2' => 'Fallst du das nicht möchtest, kannst du deine persönlichen Daten nach dem Einloggen im Menü \'Mein Profil\' löschen.',
    'subject' => 'Deine Benutzerdaten wurden verlängert',
  ),
  'userProfileRenewalAdmin' => 
  array (
    'line_1' => 'Dies ist eine Benachrichtigung, dass :adminName die Speicherung der persönlichen Daten von :userName erweitert hat. Der Verkündiger wurde ebenfalls per E-Mail benachrichtigt. Sie brauchen nichts weiter zu tun.',
    'subject' => 'Die Daten des Benutzers wurden verlängert',
  ),
  'verifyNewEmail' => 
  array (
    'line_1' => 'Du hast uns gebeten, deine E-mail Adresse zu ändern. Die Änderung wird wirksam, wenn du auf die neue E-Mail Adresse bestätigst, indem du unten auf den Button klickst.',
    'line_2' => 'Wenn du diese Änderung nicht beantragt hast, ignoriere dieses Schreiben.',
    'subject' => 'Bestätigung der neue E-mail Adresse',
  ),
);
