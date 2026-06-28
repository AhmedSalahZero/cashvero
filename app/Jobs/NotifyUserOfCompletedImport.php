<?php
namespace App\Jobs;


use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
class NotifyUserOfCompletedImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $active_job_id;
    public $company_id;
    public $model_name;
    public function __construct(User $user,$active_job_id,$companyId,string $modelName)
    {
        $this->user = $user;
        $this->active_job_id = $active_job_id;
        $this->company_id = $companyId;
        $this->model_name =$modelName;
    }

    public function handle()
    {
        DB::delete('delete from  active_jobs where id = ? and model = ?', [$this->active_job_id,$this->model_name]);
		
        toastr('Import Finished!','success');

            return redirect()->back() ;
        // $s = DB::table('active_jobs')->where('id', $this->active_job_id)->update(["status"=>"finished"]);

    }
}
