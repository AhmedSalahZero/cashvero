<?php

namespace App\Jobs;


use App\Models\ActiveJob;
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
	public $companyId;
	public function __construct(User $user, $active_job_id, $companyId = 0)
	{
		$this->user = $user;
		$this->active_job_id = $active_job_id;
		$this->companyId = $companyId;
	}

	public function handle()
	{
		DB::delete('delete from active_jobs where id = ?', [$this->active_job_id]);
		$active_job = ActiveJob::where([
			'company_id'  => $this->companyId,
			'model_name'  => 'SalesGatheringTest',
			'status'  => 'save_to_table',
		])->first();

		if ($active_job) {
			$active_job->delete();
		}
		toastr('Import Finished!', 'success');
		return redirect()->back();
	}
}
