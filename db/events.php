<?php

namespace local_suap\event;

$observers = [
    [
        'eventname'   => '\core\event\user_enrolment_created',
        'callback'    => 'local_suap_observer::user_enrolment_created',
    ],
    [
        'eventname'   => '\core\event\user_enrolment_deleted',
        'callback'    => 'local_suap_observer::user_enrolment_deleted',
    ],
    [
        'eventname'   => '\core\event\user_enrolment_updated',
        'callback'    => 'local_suap_observer::user_enrolment_updated',
    ],
    [
        'eventname'   => '\core\event\user_created',
        'callback'    => 'local_suap_observer::user_created',
    ],
    [
        'eventname'   => '\core\event\user_deleted',
        'callback'    => 'local_suap_observer::user_deleted',
    ],
    [
        'eventname'   => '\core\event\user_updated',
        'callback'    => 'local_suap_observer::user_updated',
    ],
];
