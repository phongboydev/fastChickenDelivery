<?php

namespace App\Support;

class ErrorCode
{

    /**
     * @var string Unknown error.
     * @usedby by default, whenever code is not provided
     */
    const ERR0001 = "ERR0001";

    /**
     * @var string There is still unapproved request for deleted user.
     * @usedby ApproveFlowUserObserver
     */
    const ERR0002 = "ERR0002";

    /**
     * @var string Requested user_id is not found, or current user doesn't have enough authority
     * @usedby ApproveFlowUserObserver
     */
    const ERR0003 = "ERR0003";

    /**
     * @var string There is still timesheet_shift_id request for deleted TimesheetShift.
     * @usedby TimesheetShiftObserver
     */
    const ERR0006 = "ERR0006";

    /**
     * @var string Requested period is overlapped with other registered period
     * @usedby WorktimeRegisterObserver
     */
    const ERR0004 = "ERR0004";

    /**
     * @var string Occurring error when assigning shifts.
     * @usedby SetTimesheetShiftJob
     */
    const ERR0005 = "ERR0005";
}
