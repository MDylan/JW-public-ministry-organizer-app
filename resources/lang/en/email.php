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
      'unknown' => 'Unknown.',
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
);
