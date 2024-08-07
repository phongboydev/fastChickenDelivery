<?php

namespace App\Console\Commands;

use App\Models\Formula;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FormulaSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'formulaSchedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update formula';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

      $today = Carbon::today()->format('Y-m-d');

      // Chỉ cập nhật khi công thức cha hết hạn
      $mainFormulas = Formula::whereNull('parent_id')
                            ->whereNotNull('end_effective_at')
                            ->whereDate('end_effective_at', '>', $today )
                            ->orWhere(function($query) use($today) {
                              return $query->whereNotNull('end_effective_at')
                              ->whereDate('end_effective_at', '<', $today );
                            })->get();

      $this->line("Today ... " . $today);
 
      foreach( $mainFormulas as $mainFormula ) 
      {

        $this->line("MainFormula ... " . $mainFormula->name);

        $parentId = $mainFormula->id;
        
        $parentFormula = Formula::where('parent_id', $parentId)
                              ->whereDate('begin_effective_at', '<=', $today )
                              ->whereDate('end_effective_at', '>=', $today)
                              ->whereNotNull('end_effective_at')
                              ->orWhere(function($query) use($today, $parentId) {
                                return $query->whereDate('begin_effective_at', '<=', $today )
                                      ->whereNull('end_effective_at')
                                      ->where('parent_id', $parentId);
                                })
                              ->orderBy('begin_effective_at', 'ASC')
                              ->first();
        if($parentFormula)
        {
          $this->line("Result ... " . $parentFormula->begin_effective_at . ' - ' . $parentFormula->end_effective_at . ' : ' . $parentFormula->name);

          Formula::where('id', $parentId)->update([
            'formula' => $parentFormula->formula,
            'begin_effective_at' => $parentFormula->begin_effective_at,
            'end_effective_at' => $parentFormula->end_effective_at
          ]);
        }
      }
    
    }
}
