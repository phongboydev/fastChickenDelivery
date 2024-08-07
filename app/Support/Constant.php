<?php

namespace App\Support;

class Constant
{
    const RESPONSE_STATUS_OK = "ok";
    const RESPONSE_STATUS_ERROR = "error";

    const INTERNAL_DUMMY_CLIENT_CODE = "000000";
    const INTERNAL_DUMMY_CLIENT_ID = "000000000000000000000000";

    /**
     * Roles
     */
    const ROLE_CLIENT_STAFF = 'staff';
    const ROLE_CLIENT_LEADER = 'leader';
    const ROLE_CLIENT_HR = 'hr';
    const ROLE_CLIENT_ACCOUNTANT = 'accountant';
    const ROLE_CLIENT_MANAGER = 'manager';

    const ROLE_INTERNAL_STAFF = 'staff';
    const ROLE_INTERNAL_LEADER = 'leader';
    const ROLE_INTERNAL_ACCOUNTANT = 'accountant';
    const ROLE_INTERNAL_DIRECTOR = 'director';

    const WEB = 'Web';
    const APP = 'App';

    const TYPE_USER = 'user';
    const TYPE_SYSTEM = 'system';

    const STATUS_RANK = [
        'new'           => 0,
        'processing'    => 1,
        'processed'     => 2,
        'received'      => 2,
    ];

    const CREATING_STATUS = 'creating';
    const NEW_STATUS = 'new';
    const PROCESSING_STATUS = 'processing';
    const PROCESSED_STATUS = 'processed';
    const RECEIVED_STATUS = 'received';
    const ERROR_STATUS = 'error';
    const CALC_SHEET_STATUS_DIRECTOR_REVIEW = 'director_review';
    const CALC_SHEET_STATUS_DIRECTOR_APPROCED = 'director_approced';
    const CALC_SHEET_STATUS_DIRECTOR_DECLINED = 'director_declined';
    const CALC_SHEET_STATUS_CLIENT_REVIEW = 'client_review';
    const CALC_SHEET_STATUS_CLIENT_APPROVED = 'client_approved';
    const CALC_SHEET_STATUS_CLIENT_REJECTED = 'client_rejected';
    const CALC_SHEET_STATUS_PAID = 'paid';
    const CALC_SHEET_STATUS_LEADER_DECLINED = 'leader_declined';


    // Timesheet work status
    const WORK_STATUS_PAID_LEAVE = 'Nghỉ phép HL';

    const DEFAULT_APPEND_TO_DATE = '01-01';

    // Sum salary group by
    const GROUP_BY_DEPARTMENT = 1;
    const GROUP_BY_POSITION = 2;
    const GROUP_BY_DEPARTMENT_AND_POSITION = 3;
    const TYPE_LEAVE = 'leave_request';
    const TYPE_OT = 'overtime_request';
    const TYPE_MAKEUP = 'makeup_request';
    const TYPE_BUSINESS = 'congtac_request';
    const TYPE_OT_ALL = 'ot_and_makeup';

    const OT_TYPES = [Constant::TYPE_OT, Constant::TYPE_MAKEUP];

    const INTERNAL_ACTIVATE_CLIENT = 'INTERNAL_ACTIVATE_CLIENT';
    const INTERNAL_UPDATE_CLIENT = 'INTERNAL_UPDATE_CLIENT';
    const INTERNAL_CONFIRM_UPDATED_CLIENT = 'INTERNAL_CONFIRM_UPDATED_CLIENT';
    const INTERNAL_ACTIVATE_OR_UPDATE_CLIENT = 'activate_update_client';
    const INTERNAL_APPROVED_CLIENT = 'approved_client';
    const INTERNAL_DISAPPROVED_CLIENT = 'disapprove_client';
    const CLIENT_UPDATE_EMPLOYEE_OTHERS = 'CLIENT_UPDATE_EMPLOYEE_OTHERS';
    const CLIENT_UPDATE_EMPLOYEE_BASIC = 'CLIENT_UPDATE_EMPLOYEE_BASIC';
    const CLIENT_UPDATE_EMPLOYEE_PAYROLL = 'CLIENT_UPDATE_EMPLOYEE_PAYROLL';
    const CLIENT_REQUEST_PAYROLL = 'CLIENT_REQUEST_PAYROLL';
    const CLIENT_REQUEST_CANCEL_OT = 'CLIENT_REQUEST_CANCEL_OT';
    const CLIENT_REQUEST_CANCEL_OFF = 'CLIENT_REQUEST_CANCEL_OFF';
    const CLIENT_REQUEST_CANCEL_CONG_TAC = 'CLIENT_REQUEST_CANCEL_CONG_TAC';
    const CLIENT_REQUEST_TIMESHEET = 'CLIENT_REQUEST_TIMESHEET';

    // Default checkin Timezone
    const TIMESHEET_TIMEZONE = "Asia/Ho_Chi_Minh";

    // Permissions
    const PERMISSION_CLIENT_MANAGE_CAMERA = "manage-camera-checkin";
    const PERMISSION_MANAGE_CONTRACT = "manage-contract";

    // Client employee statuses
    const CLIENT_EMPLOYEE_STATUS_WORKING = "đang làm việc";
    const CLIENT_EMPLOYEE_STATUS_UNPAID = "nghỉ không lương";
    const CLIENT_EMPLOYEE_STATUS_MATERNITY = "nghỉ thai sản";
    const CLIENT_EMPLOYEE_STATUS_QUIT = "nghỉ việc";

    // Client employee avatar
    const CLIENT_EMPLOYEE_AVATAR_DEFAULT = '/img/theme/man.png';

    const COUNTRY_LIST = ["ANDORRA", "UNITED ARAB EMIRATES", "AFGHANISTAN", "ANTIGUA AND BARBUDA", "ANGUILLA", "ALBANIA", "ARMENIA", "NETHERLANDS ANTILLES", "ANGOLA", "ANTARCTICA", "ARGENTINA", "AMERICAN SAMOA", "AUSTRIA", "AUSTRALIA", "ARUBA", "ÅLAND ISLANDS", "AZERBAIJAN", "BOSNIA AND HERZEGOVINA", "BARBADOS", "BANGLADESH", "BELGIUM", "BURKINA FASO", "BULGARIA", "BAHRAIN", "BURUNDI", "BENIN", "BERMUDA", "BRUNEI DARUSSALAM", "BOLIVIA", "BRAZIL", "BAHAMAS", "BHUTAN", "BOUVET ISLAND", "BOTSWANA", "BELARUS", "BELIZE", "CANADA", "COCOS (KEELING) ISLANDS", "CONGO, THE DEMOCRATIC REPUBLIC OF THE", "CENTRAL AFRICAN REPUBLIC", "CONGO", "SWITZERLAND", "CÔTE D'IVOIRE", "COOK ISLANDS", "CHILE", "CAMEROON", "CHINA", "COLOMBIA", "COSTA RICA", "SERBIA AND MONTENEGRO", "CUBA", "CAPE VERDE", "CHRISTMAS ISLAND", "CYPRUS", "CZECH REPUBLIC", "GERMANY", "DJIBOUTI", "DENMARK", "DOMINICA", "DOMINICAN REPUBLIC", "ALGERIA", "ECUADOR", "ESTONIA", "EGYPT", "WESTERN SAHARA", "ERITREA", "SPAIN", "ETHIOPIA", "FINLAND", "FIJI", "FALKLAND ISLANDS (MALVINAS)", "MICRONESIA, FEDERATED STATES OF", "FAROE ISLANDS", "FRANCE", "GABON", "UNITED KINGDOM", "GRENADA", "GEORGIA", "FRENCH GUIANA", "GHANA", "GIBRALTAR", "GREENLAND", "GAMBIA", "GUINEA", "GUADELOUPE", "EQUATORIAL GUINEA", "GREECE", "SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS", "GUATEMALA", "GUAM", "GUINEA-BISSAU", "GUYANA", "HONG KONG", "HEARD ISLAND AND MCDONALD ISLANDS", "HONDURAS", "CROATIA", "HAITI", "HUNGARY", "INDONESIA", "IRELAND", "ISRAEL", "INDIA", "BRITISH INDIAN OCEAN TERRITORY", "IRAQ", "IRAN, ISLAMIC REPUBLIC OF", "ICELAND", "ITALY", "JAMAICA", "JORDAN", "JAPAN", "KENYA", "KYRGYZSTAN", "CAMBODIA", "KIRIBATI", "COMOROS", "SAINT KITTS AND NEVIS", "KOREA, DEMOCRATIC PEOPLE'S REPUBLIC OF", "KOREA, REPUBLIC OF", "KUWAIT", "CAYMAN ISLANDS", "KAZAKHSTAN", "LAO PEOPLE'S DEMOCRATIC REPUBLIC", "LEBANON", "SAINT LUCIA", "LIECHTENSTEIN", "SRI LANKA", "LIBERIA", "LESOTHO", "LITHUANIA", "LUXEMBOURG", "LATVIA", "LIBYAN ARAB JAMAHIRIYA", "MOROCCO", "MONACO", "MOLDOVA, REPUBLIC OF", "MADAGASCAR", "MARSHALL ISLANDS", "MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF", "MALI", "MYANMAR", "MONGOLIA", "MACAO", "NORTHERN MARIANA ISLANDS", "MARTINIQUE", "MAURITANIA", "MONTSERRAT", "MALTA", "MAURITIUS", "MALDIVES", "MALAWI", "MEXICO", "MALAYSIA", "MOZAMBIQUE", "NAMIBIA", "NEW CALEDONIA", "NIGER", "NORFOLK ISLAND", "NIGERIA", "NICARAGUA", "NETHERLANDS", "NORWAY", "NEPAL", "NAURU", "NIUE", "NEW ZEALAND", "OMAN", "PANAMA", "PERU", "FRENCH POLYNESIA", "PAPUA NEW GUINEA", "PHILIPPINES", "PAKISTAN", "POLAND", "SAINT PIERRE AND MIQUELON", "PITCAIRN", "PUERTO RICO", "PALESTINIAN TERRITORY, OCCUPIED", "PORTUGAL", "PALAU", "PARAGUAY", "QATAR", "RÉUNION", "ROMANIA", "RUSSIAN FEDERATION", "RWANDA", "SAUDI ARABIA", "SOLOMON ISLANDS", "SEYCHELLES", "SUDAN", "SWEDEN", "SINGAPORE", "SAINT HELENA", "SLOVENIA", "SVALBARD AND JAN MAYEN", "SLOVAKIA", "SIERRA LEONE", "SAN MARINO", "SENEGAL", "SOMALIA", "SURINAME", "SAO TOME AND PRINCIPE", "EL SALVADOR", "SYRIAN ARAB REPUBLIC", "SWAZILAND", "TURKS AND CAICOS ISLANDS", "CHAD", "FRENCH SOUTHERN TERRITORIES", "TOGO", "THAILAND", "TAJIKISTAN", "TOKELAU", "TIMOR-LESTE", "TURKMENISTAN", "TUNISIA", "TONGA", "TURKEY", "TRINIDAD AND TOBAGO", "TUVALU", "TAIWAN, PROVINCE OF CHINA", "TANZANIA, UNITED REPUBLIC OF", "UKRAINE", "UGANDA", "UNITED STATES MINOR OUTLYING ISLANDS", "UNITED STATES", "URUGUAY", "UZBEKISTAN", "HOLY SEE (VATICAN CITY STATE)", "SAINT VINCENT AND THE GRENADINES", "VENEZUELA", "VIRGIN ISLANDS, BRITISH", "Vatican City State see HOLY SEE", "VIRGIN ISLANDS, U.S.", "Việt Nam", "VANUATU", "WALLIS AND FUTUNA", "SAMOA", "YEMEN", "MAYOTTE", "SOUTH AFRICA", "ZAMBIA", "ZIMBABWE"];

    // Digital Sync Api
    const API_DIGITAL_SIGN_URL = "https://crm-api.esoc.vn/";
    const API_LOGIN = "v1/api/TokenValue";
    const API_GET_DATA = "v1/api/GetKhachHangInfo";
    const API_CREATE_DATA = "v1/api/CreateDataESOC";

    const WAIT_CANCEL_APPROVE = 'await_cancel_approved';

    const TYPE_CANCEL_AP = ['CLIENT_REQUEST_CANCEL_OT', 'CLIENT_REQUEST_CANCEL_OFF', 'CLIENT_REQUEST_CANCEL_CONG_TAC', 'CLIENT_REQUEST_CANCEL_AIRLINE_TRANSPORTATION', 'CLIENT_REQUEST_CANCEL_ROAD_TRANSPORTATION'];

    /**
     * Logic các loại nghỉ phép
     * Dùng cho: Web, mobile
     * copy: frontend-common\mixins\renderless\CongSo\LeaveRequestCategories.js
     */

    const LEAVE_CATEGORIES = [
        'authorized_leave' =>
        [
            'leave_request.authorized.year_leave',
            'leave_request.authorized.self_marriage_leave',
            'leave_request.authorized.child_marriage_leave',
            'leave_request.authorized.family_lost',
            'leave_request.authorized.woman_leave',
            'leave_request.authorized.baby_care',
            'leave_request.authorized.changed_leave',
            'leave_request.authorized.covid_leave',
            'leave_request.authorized.other_leave',
        ],
        'unauthorized_leave' =>
        [
            'leave_request.unauthorized.unpaid_leave',
            'leave_request.unauthorized.pregnant_leave',
            'leave_request.unauthorized.self_sick_leave',
            'leave_request.unauthorized.child_sick',
            'leave_request.unauthorized.wife_pregnant_leave',
            'leave_request.unauthorized.prenatal_checkup_leave',
            'leave_request.unauthorized.sick_leave',
            'leave_request.unauthorized.other_leave',
        ]
    ];

    // các loại đơn của nghỉ phép
    const TYPE_REGISTER = [
        'all_day' => [
            'label' => 'all_day',
            'value' => false
        ],
        'by_the_hour' => [
            'label' => 'by_the_hour',
            'value' => true
        ],
        'half_a_morning_break' => [
            'label' => 'model.clients.half_a_morning_break',
            'value' => 2
        ],
        'half_a_afternoon_break' => [
            'label' => 'model.clients.half_a_afternoon_break',
            'value' => 3
        ],
    ];

    // các loại đơn của đơn công tác.
    const TYPE_BUSSINESS = [
        'business_trip' => [
            'label' => 'model.timesheets.work_status.di_cong_tac',
            'value' => 'business_trip'
        ],
        'business_trip_road' => [
            'label' => 'leave_request.business.road',
            'value' => 'business_trip_road'
        ],
        'business_trip_airline' => [
            'label' => 'leave_request.business.airline',
            'value' => 'business_trip_airline'
        ],
        'outside_working' => [
            'label' => 'model.worktime_register.leave_request.type.outside_working',
            'value' => 'outside_working'
        ],
        'wfh' => [
            'label' => 'model.worktime_register.leave_request.type.wfh',
            'value' => 'wfh'
        ],
        'other' => [
            'label' => 'model.client_applied_document.document_type_options.other',
            'value' => 'other'
        ],
    ];

    // Sub yype Leave
    const AUTHORIZED_LEAVE = 'authorized_leave';
    const UNAUTHORIZED_LEAVE = 'unauthorized_leave';

    const LEAVE_CATEGORIES_BY_KEY = [
        'authorized_leave' =>
        [
            'year_leave',
            'self_marriage_leave',
            'child_marriage_leave',
            'family_lost',
            'woman_leave',
            'baby_care',
            'changed_leave',
            'covid_leave',
            'other_leave'
        ],
        'unauthorized_leave' =>
        [
            'unpaid_leave',
            'pregnant_leave',
            'self_sick_leave',
            'child_sick',
            'wife_pregnant_leave',
            'prenatal_checkup_leave',
            'sick_leave',
            'other_leave'
        ]
    ];

    const ADVANCED_PERMISSION_FLOW = [
        [
            "name" => "advanced-manage-employee",
            "has_group" => true,
            "sub" => [
                [
                    "name" => "advanced-manage-employee-list",
                    "permission" => ['create', 'read', 'update', 'delete', 'import', 'export']
                ],
                [
                    "name" => "advanced-manage-employee-group",
                    "permission" => ['create', 'read', 'update', 'delete']
                ],
                [
                    "name" => "advanced-manage-employee-history-position",
                    "permission" => ['read']
                ],
            ]
        ],
        [
            "name" => "advanced-manage-timesheet",
            "has_group" => true,
            "sub" => [
                [
                    "name" => "advanced-manage-timesheet-summary",
                    "permission" => ['read', 'export']
                ],
                [
                    "name" => "advanced-manage-timesheet-working",
                    "permission" => ['read', 'update', 'import', 'export']
                ],
                [
                    "name" => "advanced-manage-timesheet-adjust-hours",
                    "permission" => ['read', 'export']
                ],
                [
                    "name" => "advanced-manage-timesheet-leave",
                    "permission" => ['read', 'update', 'import', 'export']
                ],
                [
                    "name" => "advanced-manage-timesheet-overtime",
                    "permission" => ['create', 'read', 'update', 'export']
                ],
                [
                    "name" => "advanced-manage-timesheet-outside-working-wfh",
                    "permission" => ['read', 'update', 'export']
                ],
                [
                    "name" => "advanced-manage-timesheet-timesheet-shift",
                    "permission" => ['create', 'read', 'update', 'delete', 'export']
                ]
            ]
        ],
        [
            "name" => "advanced-manage-payroll",
            "has_group" => true,
            "sub" => [
                [
                    "name" => "advanced-manage-payroll-list",
                    "permission" => ['read', 'update', 'delete', 'export']
                ],
                [
                    "name" => "advanced-manage-payroll-info",
                    "permission" => ['read']
                ],
                [
                    "name" => "advanced-manage-payroll-social-insurance",
                    "permission" => ['read', 'update', 'delete']
                ],
                [
                    "name" => "advanced-manage-payroll-social-declaration",
                    "permission" => ['create', 'read', 'update', 'delete']
                ],
                [
                    "name" => "advanced-manage-payroll-salary-history",
                    "permission" => ['create', 'read', 'update', 'delete']
                ]
            ]
        ]
    ];

    const CRUDIE = ['create', 'read', 'update', 'delete', 'import', 'export'];


    const KEY_CONDITION_COMPARE = [
        'NUMBER_WORKING_HOUR',
        'NUMBER_PAID_LEAVE_HOUR',
        'NUMBER_UNPAID_LEAVE_HOUR',
        'NUMBER_OT_HOUR',
        'NUMBER_WFH_HOURS',
        'NUMBER_OUTSIDE_WORKING_HOURS',
        'NUMBER_BUSINESS_TRIP_HOURS',
        'NUMBER_OTHER_HOURS_OF_BUSINESS_AND_WFH'
    ];
    const COMPARISON_OPERATOR = [
        'IS_GREATER_THAN',
        'IS_GREATER_OR_EQUAL_TO',
        'IS_LESS_THAN',
        'IS_LESS_OR_EQUAL_TO',
        'IS_EQUAL_TO',
        'IS_NOT_EQUAL_TO',
    ];


    const LEAVE_REQUEST_CATEGORY = [
        'authorized' => [
            'year_leave',
            'self_marriage_leave',
            'child_marriage_leave',
            'family_lost',
            'pregnant_leave',
            'woman_leave',
            'baby_care',
            'changed_leave',
            'other_leave',
            'covid_leave'
        ],
        'unauthorized' => [
            'unpaid_leave',
            'pregnant_leave',
            'self_sick_leave',
            'child_sick',
            'wife_pregnant_leave',
            'prenatal_checkup_leave',
            'other_leave',
            'sick_leave'
        ]
    ];

    const TYPE_MIME_TYPE_FILE = [
        'excel' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            '.xlsx'
        ],
        'word' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            '.docx',
        ],
        'file' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ],
        'image' => [
            'image/png',
            'image/gif',
            'image/jpeg'
        ],
        'pdf' => [
            'application/pdf'
        ],
    ];

    const OVERTIME_TYPE = 'overtime_request';
    const MAKEUP_TYPE = 'makeup_request';
    const HANET_PAGE_SIZE = 500;
    const LIST_TYPE_ADJUST_HOURS = ['CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR', 'CLIENT_REQUEST_EDITING_FLEXIBLE_TIMESHEET', 'CLIENT_REQUEST_CHANGED_SHIFT'];
    const EDIT_WORK_HOUR = 'CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR';
    const EDIT_FLEXIBLE_TIME = 'CLIENT_REQUEST_EDITING_FLEXIBLE_TIMESHEET';

    // Client type
    const CLIENT_OUTSOURCING = 'outsourcing'; // Manage by OPS
    const CLIENT_SYSTEM = 'system'; // Manage by BMS

    const TYPE_CHECK_VALIDATE_APPROVED = [
        'App\Models\WorktimeRegister',
        'App\Models\Timesheet',
        'App\Models\TimesheetShiftMapping'
    ];

    const MENU_TAB = [
        'individual'    =>  [
            'translation' => 'personal',
            'personalData' => [
                'translation' => 'model.clients.profile',
                'basicInformation' => [
                    'translation' => 'model.employee_iglocal.basic_info',
                ],
                'salary' => [
                    'translation' => 'model.history.view_salary_history',
                ],
                'wpAndVisa' => [
                    'translation' => 'model.employees.foreign_worker',
                ],
            ],
            'workingAndLeave' => [
                'translation' => 'working_leave',
                'workingTime' => [
                    'translation' => 'timesheet',
                ],
                'timeAdjustment' => [
                    'translation' => 'time_adjustment',
                ],
                'leave' => [
                    'translation' => 'model.clients.leave',
                ],
                'overtime' => [
                    'translation' => 'model.clients.overtime',
                ],
                'businessTripAndWfh' => [
                    'translation' => 'business_trip_wfh',
                ]
            ],
            'salaryAndInsurance' => [
                'translation' => 'salary_insurance',
                'payslip' => [
                    'translation' => 'payslip',
                ],
                'socialInsurance' => [
                    'translation' => 'insurance',
                ]
            ],
            'training'  =>  [
                'translation' => 'model.evaluation.training',
                'subTraining'  => [
                    'translation' => 'model.evaluation.training',
                ]
            ],
            'evaluation'    => [
                'translation' => 'model.evaluation.make_evaluation',
                'subEvaluation'  => [
                    'translation' => 'model.evaluation.make_evaluation',
                ]
            ],
            'paymentRequest' => [
                'translation' => 'approve_names.client_request_payment',
                'subPaymentRequest'  => [
                    'translation' => 'approve_names.client_request_payment',
                ]
            ],
            'contract'  => [
                'translation' => 'contract',
            ]
        ],
        'company'   => [
            'translation' => 'client',
            'approval' => [
                'translation' => 'timesheet.excel.text.approver',
                'subApproval'   => [
                    'translation' => 'timesheet.excel.text.approver',
                ]
            ],
            'listOfEmployees' => [
                'translation' => 'model.employees.list',
                'subListOfEmployees'   => [
                    'translation' => 'model.employees.list',
                ],
                'employeeGroup'   => [
                    'translation' => 'model.employees.employee_group',
                ],
                'positionHistory'   => [
                    'translation' => 'model.history.position_history',
                ],
                'listOfDependents'   => [
                    'translation' => 'list_dependents',
                ],
                'dependentProfile'   => [
                    'translation' => 'dependent_profile',
                ]
            ],
            'workingAndLeave' => [
                'translation' => 'working_leave',
                'summary'   => [
                    'translation' => 'model.clients.summary',
                ],
                'workingTime'   => [
                    'translation' => 'timesheet',
                ],
                'timeAdjustment'   => [
                    'translation' => 'time_adjustment',
                ],
                'leave'   => [
                    'translation' => 'model.clients.leave',
                ],
                'overtime'   => [
                    'translation' => 'model.clients.overtime',
                ],
                'businessTripAndWfh'   => [
                    'translation' => 'business_trip_wfh',
                ],
                'shift'   => [
                    'translation' => 'danh_sach_ca',
                ],
                'checkinHistory'   => [
                    'translation' => 'lich_su_checkin',
                ],
            ],
            'salaryAndInsurance' => [
                'translation' => 'salary_insurance',
                'payrolls'   => [
                    'translation' => 'model.clients.payroll',
                ],
                'socialInsurance'   => [
                    'translation' => 'insurance',
                ],
                'paymentOnBehalf'   => [
                    'translation' => 'manage_debitnote',
                ],
                'insuranceClaim'   => [
                    'translation' => 'model.social_security_profile.title',
                ],
                'salaryHistory'   => [
                    'translation' => 'model.history.salary_increase_history',
                ],
                'payrollComplaint'   => [
                    'translation' => 'payslip_complaint.title',
                ],
            ],
            'report' => [
                'translation' => 'report',
                'summaryReport'   => [
                    'translation' => 'model.history.salary_statistics_by_department',
                ],
                'pitReport'   => [
                    'translation' => 'model.pit_report.tab_title',
                ]
            ],
            'recruitmentAndContract' => [
                'translation' => 'recruitment_contract',
                'recruitment'   => [
                    'translation' => 'model.jobboard_job.title',
                ],
                'contract'   => [
                    'translation' => 'contract',
                ],
            ],
            'training' => [
                'translation' => 'model.evaluation.training',
                'trainingControl'   => [
                    'translation' => 'model.training_seminars.title',
                ],
                'history'   => [
                    'translation' => 'history',
                ]
            ],
            'evaluation' => [
                'translation' => 'model.evaluation.make_evaluation',
                'evaluationManagement'   => [
                    'translation' => 'manage_review',
                ],
                'personalEvaluation'   => [
                    'translation' => 'model.evaluation.do_evaluation',
                ],
            ],
            'accessManagement' => [
                'translation' => 'model.evaluation.make_evaluation',
                'workFlowSetup'   => [
                    'translation' => 'model.clients.thiet_lap_flow',
                ],
                'accessControl'   => [
                    'translation' => 'model.clients.phan_quyen_he_thong',
                ]
            ],
            'documentDelivery'   => [
                'translation' => 'model.client_applied_document.title',
            ],
            'paymentRequest' => [
                'translation' => 'approve_names.client_request_payment',
                'subPaymentRequest'   => [
                    'translation' => 'approve_names.client_request_payment',
                ],
                'supplier'   => [
                    'translation' => 'model.supplier.title',
                ]
            ],
            'setting' => [
                'translation' => 'settings',
                'wifiCheckin'   => [
                    'translation' => 'configure_wifi_checkin',
                ],
                'locationCheckin'   => [
                    'translation' => 'fields.input.location_checkin',
                ],
                'camera'   => [
                    'translation' => 'model.hanet.title',
                ],
                'workingCalender'   => [
                    'translation' => 'model.overviews.calendar',
                ],
                'configuration'   => [
                    'translation' => 'cau_hinh_chuc_nang',
                ],
                'leaveCategory'   => [
                    'translation' => 'set_up_leave_categories',
                ],
                'setupLeave'   => [
                    'translation' => 'thiet_lap_nghi_phep',
                ],
                'setupOvertime'   => [
                    'translation' => 'thiet_lap_tang_ca',
                ],
                'shiftControl'   => [
                    'translation' => 'quan_ly_ca',
                ],
                'holidaySchedule'   => [
                    'translation' => 'quan_ly_ngay_le',
                ],
                'divisionManagement'   => [
                    'translation' => 'management.department',
                ],
                'positionManagement'   => [
                    'translation' => 'management.position',
                ],
                'flexibleTimesheet'   => [
                    'translation' => 'timesheet_linh_hoat',
                ],
                'timeCheck'   => [
                    'translation' => 'quan_ly_dieu_kien_so_sanh',
                ]
            ],
            'payrollDesign' => [
                'translation' => 'payroll_design',
                'createPayroll'   => [
                    'translation' => 'model.clients.create_payroll',
                ],
                'salaryCalculationTemplate'   => [
                    'translation' => 'model.payroll.salary_calculation_form',
                ],
                'payrollTemplate'   => [
                    'translation' => 'fields.input.payroll_template',
                ],
                'variableDefinition'   => [
                    'translation' => 'import_variable_definition',
                ],
                'formulaManagement'   => [
                    'translation' => 'recipe_management',
                ]
            ],
        ]
    ];

    const LIST_S_VARIABLE_WITH_CONDITION = [
        'NUMBER_WORKING_HOUR' => [
            'name_variable' => 'S_NUMBER_WORKING_DAY_SATISFY_CONDITION',
            'readable_name'   => 'Số ngày đi làm thỏa mãn điều kiện cho trước'
        ],
        'NUMBER_PAID_LEAVE_HOUR' => [
            'name_variable' => 'S_NUMBER_PAID_LEAVE_DAY_SATISFY_CONDITION',
            'readable_name'   => 'Số ngày nghỉ phép hưởng lương thỏa mãn điều kiện cho trước'
        ],
        'NUMBER_UNPAID_LEAVE_HOUR' => [
            'name_variable' => 'S_NUMBER_UNPAID_LEAVE_DAY_SATISFY_CONDITION',
            'readable_name'   => 'Số ngày nghỉ phép không hưởng lương thỏa mãn điều kiện cho trước'
        ],
        'NUMBER_OT_HOUR' => [
            'name_variable' => 'S_NUMBER_OT_DAY_SATISFY_CONDITION',
            'readable_name'   => 'Số ngày OT thỏa mãn điều kiện cho trước'
        ],
        'NUMBER_WFH_HOURS' => [
            'name_variable' => 'S_WFH_COUNT',
            'readable_name'   => 'Đếm số lượng ngày wfh được duyệt trong kỳ tính lương'
        ],
        'NUMBER_OUTSIDE_WORKING_HOURS' => [
            'name_variable' => 'S_OUTSIDE_WORKING_DAYS',
            'readable_name'   => 'Số ngày làm việc bên ngoài'
        ],
        'NUMBER_BUSINESS_TRIP_HOURS' => [
            'name_variable' => 'S_BUSINESS_TRIP_DAYS',
            'readable_name'   => 'Số ngày đi công tác'
        ],
        'NUMBER_OTHER_HOURS_OF_BUSINESS_AND_WFH' => [
            'name_variable' => 'S_COUNT_DAY_OF_OTHER_BUSINESS_AND_WFH',
            'readable_name'   => 'Số ngày xin đơn khác (trong tab của Business trip & WFH)'
        ],
    ];

    const LIST_S_VARIABLE_WITH_NOT_CONDITION = [
        'S_WFH_COUNT' => [
            'readable_name' => 'Đếm số lượng ngày wfh được duyệt trong kỳ tính lương',
            'variable_name' => 'S_WFH_COUNT',
            'variable_value' => 0
        ],
        'S_OUTSIDE_WORKING_DAYS' => [
            'readable_name' => 'Số ngày làm việc bên ngoài',
            'variable_name' => 'S_OUTSIDE_WORKING_DAYS',
            'variable_value' => 0,
        ],
        'S_BUSINESS_TRIP_DAYS' => [
            'readable_name' => 'Số ngày đi công tác',
            'variable_name' => 'S_BUSINESS_TRIP_DAYS',
            'variable_value' => 0,
        ],
        'S_COUNT_DAY_OF_OTHER_BUSINESS_AND_WFH' => [
            'readable_name' => 'Số ngày xin đơn khác (trong tab của Business trip & WFH)',
            'variable_name' => 'S_COUNT_DAY_OF_OTHER_BUSINESS_AND_WFH',
            'variable_value' => 0,
        ]
    ];

    const LEAVE_CATEGORY_TRANS = [
        'authorized_leave.year_leave' => 'leave_request.authorized.year_leave',
        'authorized_leave.self_marriage_leave' => 'leave_request.authorized.self_marriage_leave',
        'authorized_leave.child_marriage_leave' => 'leave_request.authorized.child_marriage_leave',
        'authorized_leave.family_lost' => 'leave_request.authorized.family_lost',
        'authorized_leave.pregnant_leave' => 'leave_request.authorized.pregnant_leave',
        'authorized_leave.woman_leave' => 'leave_request.authorized.woman_leave',
        'authorized_leave.baby_care' => 'leave_request.authorized.baby_care',
        'authorized_leave.changed_leave' => 'leave_request.authorized.changed_leave',
        'authorized_leave.covid_leave' => 'leave_request.authorized.covid_leave',
        'authorized_leave.other_leave' => 'leave_request.authorized.other_leave',
        'authorized_leave.sick_leave' => 'leave_request.authorized.sick_leave',
        'unauthorized_leave.unpaid_leave' => 'leave_request.authorized.unpaid_leave',
        'unauthorized_leave.pregnant_leave' => 'leave_request.authorized.pregnant_leave',
        'unauthorized_leave.self_sick_leave' => 'leave_request.authorized.self_sick_leave',
        'unauthorized_leave.child_sick' => 'leave_request.authorized.child_sick',
        'unauthorized_leave.wife_pregnant_leave' => 'leave_request.authorized.wife_pregnant_leave',
        'unauthorized_leave.prenatal_checkup_leave' => 'leave_request.unauthorized.prenatal_checkup_leave',
        'unauthorized_leave.sick_leave' => 'leave_request.unauthorized.sick_leave',
        'unauthorized_leave.other_leave' => 'leave_request.authorized.other_leave'
    ];

    const TYPE_FLEXIBLE_TIMESHEET = 'applied_flexible_time';
    const APPLIED_CORE_TIME = 'applied_core_time';


    const TYPE_ADVANCED_APPROVE = [
        'CLIENT_REQUEST_OT',
        'CLIENT_REQUEST_OFF',
        'CLIENT_REQUEST_CONG_TAC',
        'CLIENT_REQUEST_PAYMENT',
        'CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR',
        'CLIENT_REQUEST_ROAD_TRANSPORTATION',
        'CLIENT_REQUEST_AIRLINE_TRANSPORTATION',
        'CLIENT_REQUEST_TIMESHEET'
    ];

    const TYPE_CANCEL_ADVANCED_APPROVE = [
        'CLIENT_REQUEST_CANCEL_OT',
        'CLIENT_REQUEST_CANCEL_OFF',
        'CLIENT_REQUEST_CANCEL_CONG_TAC',
        'CLIENT_REQUEST_CANCEL_ROAD_TRANSPORTATION',
        'CLIENT_REQUEST_CANCEL_AIRLINE_TRANSPORTATION'
    ];

    const TYPE_TRANSPORTATION = [
        'road',
        'airline'
    ];

    const PENDING_STATUS = "pending";

    const APPROVE_STATUS = "approved";

    const DECLINED_STATUS = "declined";

    const TYPE_BUSINESS_TRIP = "business_trip";

    const TYPE_OUTSIDE_WORKING = "outside_working";

    const TYPE_WFH = "wfh";

    const TYPE_OTHER_BUSINESS = "other";

    const ADVANCED =  'advanced';

}
