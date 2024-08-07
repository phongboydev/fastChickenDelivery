<?php

namespace App\GraphQL\Mutations;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use App\Models\CalculationSheetClientEmployee as CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable as CalculationSheetVariable;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\CustomException;
use Illuminate\Support\Facades\Storage;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\View;

class CalculationSheetClientEmployeeMutator

{
    public function calculationSheetClientEmployee($root, array $args)
    {
        $filtered = isset($args['filtered']) ? $args['filtered'] : '';
        if ($filtered) {
            return CalculationSheetClientEmployee::with('clientEmployee')
                ->whereHas('clientEmployee', function ($query) use ($filtered) {
                    $query->where('client_employees.full_name', 'LIKE', "%$filtered%")
                        ->orWhere('client_employees.code', 'LIKE', "%$filtered%");
                });
        } else {
            return CalculationSheetClientEmployee::with('calculationSheet')->with('clientEmployee');
        }
    }

    public function detail($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $calculationSheetClientEmployee = CalculationSheetClientEmployee::whereId($args['id'])
            ->with('calculationSheet')
            ->with('clientEmployee')
            ->firstOrFail();

        if ($user->can('view', $calculationSheetClientEmployee)) {
            $calculationSheetClientEmployee = $calculationSheetClientEmployee->toArray();
        } else {
            throw new AuthenticationException(__("error.permission"));
        }

        if ($calculationSheetClientEmployee) {
            $calculationSheetVariable = CalculationSheetVariable::select('*')
                ->where('calculation_sheet_id', '=', $calculationSheetClientEmployee['calculation_sheet']['id'])
                ->where('client_employee_id', '=', $calculationSheetClientEmployee['client_employee']['id'])
                ->get()->toArray();

            $calculationSheetClientEmployee['calculation_sheet_variable'] = $calculationSheetVariable;
        }

        return $calculationSheetClientEmployee;
    }

    public function listDetail($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $calculationSheetId = $args['calculation_sheet_id'];
        $employees = $args['employees'] ?? [];
        $group_ids = $args['employee_group_ids'] ?? [];
        $orderby = $args['orderby'] ?? 'id';
        $order = $args['order'] ?? 'ASC';
        $perpage = $args['perpage'] ?? 10;
        $page = $args['page'] ?? 1;
        $calculationSheetVariables = CalculationSheetVariable::select('*')
            ->where('calculation_sheet_id', '=', $calculationSheetId)
            ->get()
            ->groupBy('client_employee_id');
        $calculationSheetClientEmployeeLists = CalculationSheetClientEmployee::select('*')
            ->with(['clientEmployee' => function ($q) use ($group_ids) {
                if ($group_ids) {
                    $q->whereHas('clientEmployeeGroupAssignment', function ($sub) use ($group_ids) {
                        $sub->whereIn('client_employee_group_id', $group_ids);
                    });
                }
            }])
            ->whereHas('clientEmployee', function ($q) use ($group_ids) {
                if ($group_ids) {
                    $q->whereHas('clientEmployeeGroupAssignment', function ($sub) use ($group_ids) {
                        $sub->whereIn('client_employee_group_id', $group_ids);
                    });
                }
            })
            ->where('calculation_sheet_id', '=', $calculationSheetId)
            ->authUserAccessible();

        if ($employees) {
            $calculationSheetClientEmployeeLists->whereIn('client_employee_id', $employees);
        }

        $calculationSheetClientEmployeeLists = $calculationSheetClientEmployeeLists->orderBy($orderby, $order)->paginate($perpage, ['*'], 'page', $page);

        if ($calculationSheetClientEmployeeLists->isNotEmpty()) {
            foreach ($calculationSheetClientEmployeeLists as &$item) {
                if (!empty($calculationSheetVariables[$item['client_employee_id']])) {
                    $item['calculationSheetVariable'] = $calculationSheetVariables[$item['client_employee_id']];
                }
            }
        }

        return [
            'data'       => $calculationSheetClientEmployeeLists,
            'pagination' => [
                'total'        => $calculationSheetClientEmployeeLists->total(),
                'count'        => $calculationSheetClientEmployeeLists->count(),
                'per_page'     => $calculationSheetClientEmployeeLists->perPage(),
                'current_page' => $calculationSheetClientEmployeeLists->currentPage(),
                'total_pages'  => $calculationSheetClientEmployeeLists->lastPage()
            ],
        ];
    }

    public function update($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $id = (isset($args['id'])) ? $args['id'] : '';
        $calculationSheetId = (isset($args['filter_calculation_sheet_id'])) ? $args['filter_calculation_sheet_id'] : '';
        $clientEmployeeId = (isset($args['filter_client_employee_id'])) ? $args['filter_client_employee_id'] : '';
        // Neu set id thi ko quan tam cac filter con lai
        if (isset($args['id'])) {
            $calculationSheetId = null;
            $clientEmployeeId = null;
        }

        $calculationSheetClientEmployees = CalculationSheetClientEmployee::select('*')
            ->where(function ($query) use ($id) {
                if ($id) {
                    $query->where('id', '=', $id);
                }
            })
            ->where(function ($query) use ($calculationSheetId) {
                if ($calculationSheetId) {
                    $query->where('calculation_sheet_id', '=', $calculationSheetId);
                }
            })
            ->where(function ($query) use ($clientEmployeeId) {
                if ($clientEmployeeId) {
                    $query->where('client_employee_id', '=', $clientEmployeeId);
                }
            })
            ->get()->toArray();

        $modelUpdate = array();

        if ($calculationSheetClientEmployees) {
            DB::transaction(function () use ($args, $calculationSheetClientEmployees, &$modelUpdate, $user) {
                foreach ($calculationSheetClientEmployees as $calculationSheetClientEmployee) {
                    // Get model instance
                    $model = CalculationSheetClientEmployee::findOrFail($calculationSheetClientEmployee['id']);
                    $model->calculated_value = (isset($args['calculated_value'])) ? $args['calculated_value'] : 0;

                    $model->is_disabled = (isset($args['is_disabled'])) ? $args['is_disabled'] : null;
                    if (isset($args['id'])) {
                        $model->calculation_sheet_id = $args['calculation_sheet_id'];
                        $model->client_employee_id = $args['client_employee_id'];
                    }

                    if ($user->can('update', $model)) {
                        // Save model
                        $model->saveOrFail();
                        array_push($modelUpdate, $model);
                    } else {
                        throw new CustomException(
                            'You are not authorized to access updateCalculationSheetStatus.',
                            'AuthorizedException'
                        );
                    }
                }
            });
        }

        return $modelUpdate;
    }

    public function delete($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $id = (isset($args['id'])) ? $args['id'] : '';
        $calculationSheetId = (isset($args['filter_calculation_sheet_id'])) ? $args['filter_calculation_sheet_id'] : '';
        $clientEmployeeId = (isset($args['filter_client_employee_id'])) ? $args['filter_client_employee_id'] : '';

        // Neu set id thi ko quan tam cac filter con lai
        if (isset($args['id'])) {
            $calculationSheetId = null;
            $clientEmployeeId = null;
        }

        $calculationSheetClientEmployees = CalculationSheetClientEmployee::select('*')
            ->where(function ($query) use ($id) {
                if ($id) {
                    $query->where('id', '=', $id);
                }
            })
            ->where(function ($query) use ($calculationSheetId) {
                if ($calculationSheetId) {
                    $query->where('calculation_sheet_id', '=', $calculationSheetId);
                }
            })
            ->where(function ($query) use ($clientEmployeeId) {
                if ($clientEmployeeId) {
                    $query->where('client_employee_id', '=', $clientEmployeeId);
                }
            })
            ->get()->toArray();

        $modelDelete = array();
        if ($calculationSheetClientEmployees) {
            DB::transaction(function () use ($args, $calculationSheetClientEmployees, &$modelDelete, $user) {
                foreach ($calculationSheetClientEmployees as $calculationSheetClientEmployee) {
                    // Get model instance
                    $model = CalculationSheetClientEmployee::findOrFail($calculationSheetClientEmployee['id']);

                    if ($user->can('delete', $model)) {
                        // Delete logic model
                        $model->delete();
                        array_push($modelDelete, $model);
                    } else {
                        throw new CustomException(
                            'You are not authorized to access deleteCalculationSheetStatus.',
                            'AuthorizedException'
                        );
                    }
                }
            });
        }

        return $modelDelete;
    }

    public function calculationSheetClientEmployeeByUserLogin($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $id = isset($args['id']) ? $args['id'] : '';

        $item = CalculationSheetClientEmployee::select('*')
            ->where('calculation_sheet_id', '=', $id)
            ->with('calculationSheet')
            ->with('clientEmployee')
            ->where('client_employee_id', '=', $user->clientEmployee->id)
            ->whereHas('calculationSheet', function (Builder $query) {
                $query->where(function ($query) {
                    $query->whereNull('calculation_sheets.payslip_date')
                        ->orWhere('calculation_sheets.payslip_date', '<=', date('Y-m-d'));
                });
                $query->where('enable_show_payslip_for_employee', 1);
                $query->whereIn('calculation_sheets.status', ['client_approved', 'paid']);
            })->first();

        if (empty($item)) {
            return null;
        }

        $result = $item->toArray();
        $calculationSheetVariable = CalculationSheetVariable::select('*')
            ->where('calculation_sheet_id', '=', $result['calculation_sheet']['id'])
            ->where('client_employee_id', '=', $result['client_employee']['id'])
            ->get()
            ->toArray();
        $result['calculationSheetVariable'] = $calculationSheetVariable;
        return $result;
    }

    public function calculationSheetClientEmployeesByUserLogin($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $filterName = isset($args['filter_name']) ? '%' . trim($args['filter_name']) . '%' : '%%';
        $filterMonth = isset($args['filter_month']) ? $args['filter_month'] : '';
        $filterYear = isset($args['filter_year']) ? $args['filter_year'] : '';

        $perpage = isset($args['perpage']) ? $args['perpage'] : 10;
        $page = isset($args['page']) ? $args['page'] : '1';
        $clientEmployeeId = $user->clientEmployee->id;
        $calculationSheetClientEmployeeLists = CalculationSheetClientEmployee::query()
            ->with('calculationSheet')
            ->with('clientEmployee')
            ->where('client_employee_id', '=', $clientEmployeeId)
            ->whereHas('calculationSheet', function (Builder $query) use ($filterMonth, $filterName, $filterYear, $clientEmployeeId) {
                if ($filterName) {
                    $query->where('calculation_sheets.name', 'like', $filterName);
                }
                if ($filterMonth) {
                    $query->where('calculation_sheets.month', '=', $filterMonth);
                }
                if ($filterYear) {
                    $query->where('calculation_sheets.year', '=', $filterYear);
                }
                $query->where('list_employee_notify_ids', 'like', "%\"{$clientEmployeeId}\"%");
                $query->where(function ($query) {
                    $query->whereNull('calculation_sheets.payslip_date')
                        ->orWhere('calculation_sheets.payslip_date', '<=', date('Y-m-d'));
                });
                $query->where('enable_show_payslip_for_employee', 1);
                $query->whereIn('calculation_sheets.status', ['client_approved', 'paid']);
                $query->orderBy('calculation_sheets.year', 'DESC')
                    ->orderBy('calculation_sheets.month', 'DESC');
            })
            // Nhut: custom attirbute will not work if there is join statement
            // ->leftJoin('calculation_sheets', 'calculation_sheet_client_employees.calculation_sheet_id', '=', 'calculation_sheets.id')
            ->orderBy('created_at', 'DESC')
            ->paginate($perpage, ['*'], 'page', $page);
        if (!empty($calculationSheetClientEmployeeLists)) {
            foreach ($calculationSheetClientEmployeeLists as $item) {
                $item['calculationSheetVariable'] = [];
            }
        }

        return [
            'data'       => $calculationSheetClientEmployeeLists,
            'pagination' => [
                'total'        => $calculationSheetClientEmployeeLists->total(),
                'count'        => $calculationSheetClientEmployeeLists->count(),
                'per_page'     => $calculationSheetClientEmployeeLists->perPage(),
                'current_page' => $calculationSheetClientEmployeeLists->currentPage(),
                'total_pages'  => $calculationSheetClientEmployeeLists->lastPage()
            ],
        ];
    }

    public function exportPDF($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {

        $user = Auth::user();

        $id = isset($args['id']) ? $args['id'] : '';

        $item = CalculationSheetClientEmployee::select('*')
            ->where('id', '=', $id)
            ->with('calculationSheet')
            ->with('clientEmployee')
            ->where('client_employee_id', '=', $user->clientEmployee->id)->firstOrFail()->toArray();

        $total = 0;
        $date = $item['calculation_sheet']['month'] . '/' . $item['calculation_sheet']['year'];

        if ($item) {
            $calculationSheetVariable = CalculationSheetVariable::select('*')
                ->where('calculation_sheet_id', '=', $item['calculation_sheet']['id'])
                ->where('client_employee_id', '=', $item['client_employee']['id'])
                ->get()->toArray();

            $item['calculationSheetVariable'] = $calculationSheetVariable;
            $total = $item['calculated_value'];
        }

        $payslip_columns_setting = json_decode($item['calculation_sheet']['payslip_columns_setting'], true);

        if ($payslip_columns_setting) {

            $cacKhoanKhauTru = $payslip_columns_setting['cacKhoanKhauTru'] ? $payslip_columns_setting['cacKhoanKhauTru'] : [];
            $cacKhoanThuNhap = $payslip_columns_setting['cacKhoanThuNhap'] ? $payslip_columns_setting['cacKhoanThuNhap'] : [];

            if ($cacKhoanKhauTru) {

                $tongkhautru = 0;

                foreach ($cacKhoanKhauTru as &$c) {
                    foreach ($item['calculationSheetVariable'] as $v) {
                        if ($c['variable_name'] == $v['variable_name']) {
                            $c['variable_value'] = $v['variable_value'] ? $v['variable_value'] : 0;
                        }
                    }

                    $tongkhautru += $c['variable_value'];
                }

                array_push($cacKhoanKhauTru, [
                    'id' => 'tongkhautru',
                    'variable_name' => 'tongkhautru',
                    'readable_name' => 'Tổng khấu trừ',
                    'variable_value' => number_format($tongkhautru)
                ]);
            }

            if ($cacKhoanThuNhap) {

                $tongthunhap = 0;

                foreach ($cacKhoanThuNhap as &$c) {
                    foreach ($item['calculationSheetVariable'] as $v) {
                        if ($c['variable_name'] == $v['variable_name']) {
                            $c['variable_value'] = $v['variable_value'] ? $v['variable_value'] : 0;
                        }
                    }

                    $tongthunhap += $c['variable_value'];
                }

                array_push($cacKhoanThuNhap, [
                    'id' => 'tongthunhap',
                    'variable_name' => 'tongthunhap',
                    'readable_name' => 'Tổng thu nhập',
                    'variable_value' => number_format($tongthunhap)
                ]);
            }
        }

        $calVariables = [];

        if (!empty($item)) {
            foreach ($item['calculationSheetVariable'] as $c) {
                $calVariables[$c['variable_name']] = $c['variable_value'];
            }
        }

        $dompdf = new Dompdf();

        $pdfHTML = mb_convert_encoding(View::make('exports.payslip', [
            'employee' => $user->clientEmployee,
            'calVariables' => $calVariables,
            'cacKhoanKhauTru' => $cacKhoanKhauTru,
            'cacKhoanThuNhap' => $cacKhoanThuNhap,
            'total' => $total,
            'date' => $date
        ]), 'HTML-ENTITIES', 'UTF-8');

        $dompdf->loadHtml($pdfHTML);
        $dompdf->set_paper('a4', 'landscape');
        $dompdf->render();

        $storagePath  = Storage::disk('public')->getDriver()->getAdapter()->getPathPrefix();

        $fileName = "payslip_{$item['calculation_sheet']['month']}_{$item['calculation_sheet']['year']}.pdf";

        $filePath = $storagePath . $fileName;

        $fileHandle = fopen($filePath, 'w');

        fwrite($fileHandle, $dompdf->output());

        $response =  array(
            'name' => $fileName,
            'file' => "data:application/pdf;base64," . base64_encode(file_get_contents($filePath))
        );

        unlink($filePath);

        return json_encode($response);
    }
}
