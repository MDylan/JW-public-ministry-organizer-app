<?php

return array (
  'GroupUserLogout' => 
  array (
    'line_1' => 'We are informing you that you have left the :groupName group. You will no longer be able to see the calendar of the group. Also, your future services in this group have been deleted.',
    'line_2' => 'Your group membership deletion has been initiated by :userName. If this is not you, you shall contact him to discuss the reasons.',
    'subject' => 'You have left the :groupName group.',
  ),
  'ParentGroupAttached' => 
  array (
    'line_1' => 'We are informing you that a new sub-group has been assigned in your :groupName group.',
    'line_2' => 'From now on, all publishers will be copied automatically into the sub-group.',
    'line_3' => 'Sub-group name: :childGroupName',
    'line_4' => 'The modification has been performed by :userName.',
    'line_5' => 'If you wish to change this setting, please go to the "Publishers" menu and detach the link.',
    'subject' => 'Important: A new main group has been set.',
  ),
  'ParentGroupDetached' => 
  array (
    'line_1' => 'We are informing you that your :childGroupName group has been detached from the foregoing main group.',
    'line_2' => 'Publishers will be not be copied automatically into this group any more.',
    'line_3' => 'The name of the foregoing main group: :groupName',
    'line_4' => 'The modification has been performed by :userName.',
    'line_5' => 'If you wish to modify this setting, please go to the "Publishers" menu to reattach the groups.',
    'subject' => 'Important: The main group setting has been detached.',
  ),
  'anonymize' => 
  array (
    'line_1' => 'We are informing you that your personal data will soon be deleted from the :appName site due to GDPR regulations. Following deletion, you will not be able to log in.',
    'line_2' => 'If you do not want your data to be deleted, please log in to the site once by :lastDate at the latest.',
    'subject' => 'IMPORTANT: Your access will soon be revoked.',
  ),
  'anonymizeAdmin' => 
  array (
    'line_1' => 'We are informing you that the following (inactive) publisher\'s account and data soon will be deleted due to GDPR regulations. The publisher will not be able to log in after deletion. The publisher has also received an email notification about this.',
    'line_2' => 'This is necessary because it has been too long since they have last logged in.',
    'line_3' => 'There are two options to prevent this from happening:',
    'line_4' => '1. The publisher performs a login before the given due date.',
    'line_5' => '2. In the "Publishers" menu, you request to preserve the data of the publisher.',
    'line_6' => 'Group: :group',
    'line_7' => 'Affected publishers (with due date):',
    'subject' => 'IMPORTANT: Some users soon will be deleted from your group.',
  ),
  'dear' => 'Dear',
  'deletePersonalData' => 
  array (
    'line_1' => 'You have requested us to delete your personal data.',
    'line_2' => 'Please confirm your request by clicking on the link below. By clicking on the button, deletion will be performed automatically.',
    'line_3' => 'For safety reasons, this link remains valid for 1 hour. If you are not the one who requested the deletion of your personal data, do not click on the link. Change your password immediately, because apparently somebody else has used your account.',
    'subject' => 'Deleting personal data',
  ),
  'event' => 
  array (
    'created' => 
    array (
      'line_1' => 'We are informing you that :userName has scheduled the following service for you in the :groupName group.',
      'line_2' => ':newServiceDate',
      'line_3' => 'Enjoy your service! :)',
      'subject' => 'Your planned service on :date (:groupName)',
    ),
    'deleted' => 
    array (
      'line_1' => 'We are informing you that your following service has been deleted in the :groupName group:',
      'line_2' => ':oldServiceDate',
      'line_3' => 'Reason for deletion: :reason',
      'line_4' => 'Deletion has been initiated by :userName. He can provide further information.',
      'subject' => 'Your service on :date has been deleted! (:groupName)',
    ),
    'deleted_to_admin' => 
    array (
      'line_1' => 'We are informing you that the following service in the :groupName group has been deleted:',
      'line_2' => ':oldServiceDate',
      'line_3' => 'Reason for deletion: :reason',
      'line_4' => 'Deletion has been initiated by :userName. He can provide further information.',
      'subject' => 'Service on :date has been deleted! (:groupName)',
    ),
    'deletion_reasons' => 
    array (
      'modified_service_time' => 'The time of the service on this day has been modified, and your planned service did not fit into the new service time.',
      'service_day_deleted' => 'No further services can be scheduled for this day, so the service of all members have been deleted.',
      'unknown' => 'Unknown',
      'user_logout' => 'You have left the group, and so your future services in the group have been deleted.',
    ),
    'modified' => 
    array (
      'line_1' => 'We are informing you that your following service in the :groupName group has been modified:',
      'line_2' => 'Original service date: :oldServiceDate',
      'line_3' => 'New service date: :newServiceDate',
      'line_4' => 'Please plan your service accordingly.',
      'line_5' => 'Reason for modification: :reason',
      'line_6' => 'Modification has been initiated by :userName. He can provide further information.',
      'subject' => 'Your service scheduled for :date has been modified. (:groupName)',
    ),
    'modify_reasons' => 
    array (
      'modified_service_time' => 'The time of the service on this day has been modified, and your planned service did not fit into the new service time.',
      'unknown' => 'Unknown',
    ),
    'status_changed' => 
    array (
      0 => 
      array (
        'line_1' => 'We are informing you that your following service in the :groupName group is in the approval process. One of the group servants will soon decide if the time/date is feasible. You will be notified about the decision. Until his feedback arrives, please do not consider the scheduled service as fixed.',
        'line_2' => ':newServiceDate',
        'subject' => 'Your service is in the approval process :date',
      ),
      1 => 
      array (
        'line_1' => 'We are informing you that the following service in the :groupName group has been approved. Enjoy your service! :)',
        'line_2' => ':newServiceDate',
        'subject' => 'Your scheduled service has been approved :date!',
      ),
      2 => 
      array (
        'line_1' => 'We are informing you that the following service in the :groupName group has been declined. Please choose an other time/date.',
        'line_2' => ':newServiceDate',
        'subject' => 'The scheduled service has been declined.',
      ),
    ),
  ),
  'finishRegistration' => 
  array (
    'done' => 
    array (
      'line_1' => 'Welcome as one of the users of this page! Now you can log in using your previously set password. You can accept or decline incoming group invitations in the "Groups" menu. For further information on how to use the page, please visit the "Help" menu.',
      'subject' => 'Registration successfully finished!',
    ),
    'line_1' => 'We are informing you that :groupAdmin has invited you to become the user of the :appName page.',
    'line_2' => 'Your registration has not become valid yet. You have :day days to confirm your registration by clicking on the link below. In absence of your confirmation, your account will be deleted automatically.',
    'line_3' => 'Username: :userMail',
    'line_4' => 'During the registration confirmation process, you will be able to set your new password.',
    'line_5' => 'The link below can be used only for confirming your registration.',
    'subject' => 'You have been invited to become the user of the :appName page.',
  ),
  'footer' => 'This is an automatically generated email. Please, do not reply.',
  'groupCreator' => 
  array (
    'line_1' => 'We are informing you that you have been given the opportunity to create a new group. You can do this in the "Groups" menu.',
    'line_2' => 'Please review the videos in the Help menu for see your options. Also, where possible, descriptions will help you so please read these when using for the first time.',
    'subject' => 'You have been granted "Group creator" permission',
  ),
  'groupUserAdded' => 
  array (
    'line_1' => 'This is an automatically generated notification about :groupAdmin inviting you to join the :groupName group.',
    'line_2' => 'After login, you shall accept or decline this invitation in the "Groups" menu.',
    'subject' => 'Invitation to the :groupName group',
  ),
  'loginData' => 
  array (
    'line_1' => ':groupAdmin has created an account for you on the page.',
    'line_2' => 'Login data:',
    'line_3' => 'Username: :userMail',
    'line_4' => 'Password: :userPassword',
    'line_5' => 'After login, please make sure to change this password under the "My profile" menu.',
    'subject' => 'Login data',
  ),
  'messages' => 
  array (
    'line_1' => 'An urgent message has been posted on the message board by :userName in the :groupName group.',
    'line_2' => 'The content of the message:',
    'line_3' => 'You can reply to the message on the message board or by using the personal contact details of the publisher. Please DO NOT reply to this email because it is not the publisher who will receive the reply.',
    'subject' => 'Urgent message of the following publisher: :userName',
  ),
  'newadmin' => 
  array (
    'line_1' => 'This is an automatically generated notification about :newAdmin being assigned to be the administrator of your page.',
    'line_2' => 'Assigned by: :adminBy',
    'line_3' => 'If this proves to be a mistake, please revoke his Administrator access permission as soon as possible.',
    'subject' => 'A new administrator has been assigned.',
  ),
  'profileChanged' => 
  array (
    'line_1' => 'We are informing you that your name or your telephone number on the website has been modified by :userName',
    'line_2' => 'Your old data:',
    'line_3' => 'Your new data:',
    'line_4' => 'If you notice that any of your new data is incorrect, you can modify it in the "My profile" menu after login.',
    'subject' => 'We have modified your personal data.',
  ),
  'registerFail' => 'If you are not the one who performed the registration, it will be deleted automatically after 48 hours.',
  'registerWelcome' => 'Successful registration on :url. Please click on the link below to confirm your registration.',
  'signature' => 'Your brothers, 
The authors of the Help Service Organizer page',
  'testmail' => 
  array (
    'line_1' => 'This is a test notification. If you have received this, it means that the email settings are correct.',
    'line_2' => 'Thank you for using this app :)',
    'subject' => 'Test notification.',
  ),
  'unsubscribe' => 'If you do not wish to receive such emails in the future, you may opt out in the "My Profile" menu.',
  'userProfileRenewal' => 
  array (
    'line_1' => 'We are informing you that :adminName has requested that your data should be preserved on the :appName site, though you have not logged in for a long time. As a result of this request, your account will not be deleted and your data will continue to be preserved.',
    'line_2' => 'If this is againts your will, then you can delete your personal data in the "My profile" menu after login.',
    'subject' => 'The preservation period of your user data has been extended.',
  ),
  'userProfileRenewalAdmin' => 
  array (
    'line_1' => 'We are informing you that :adminName has extended the preservation period of the personal data of :userName. The publisher has also been informed about this in email. You do not need to take any actions.',
    'subject' => 'The preservation period of the user\'s data has been extended.',
  ),
);
