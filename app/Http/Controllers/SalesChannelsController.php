<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesChannelRequest;
use App\Models\CashVeroSalesChannel;
use App\Models\Company;

/**
 * Sales Channels — Vue SimpleMasterList (no rename cascade into invoices).
 */
class SalesChannelsController
{
    public function index(Company $company)
    {
        $items = $company->salesChannels()->orderBy('name')->get();

        return \Inertia\Inertia::render('Settings/SimpleMasterList', [
            'company' => ['id' => $company->id],
            'title' => 'Sales Channels',
            'subtitle' => 'Used to categorize customer invoices by sales channel',
            'itemLabel' => 'Sales Channel',
            'items' => $items->map(fn (CashVeroSalesChannel $item) => [
                'id' => $item->id,
                'name' => $item->getName(),
                'update_url' => route('sales.channels.update', ['company' => $company->id, 'salesChannel' => $item->id]),
                'delete_url' => route('sales.channels.destroy', ['company' => $company->id, 'salesChannel' => $item->id]),
            ])->values(),
            'createUrl' => route('sales.channels.store', ['company' => $company->id]),
        ]);
    }

    public function store(Company $company, StoreSalesChannelRequest $request)
    {
        CashVeroSalesChannel::create([
            'name' => $request->get('name'),
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('sales.channels.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
    }

    public function update(Company $company, StoreSalesChannelRequest $request, CashVeroSalesChannel $salesChannel)
    {
        $salesChannel->update([
            'name' => $request->get('name'),
        ]);

        return redirect()
            ->route('sales.channels.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, CashVeroSalesChannel $salesChannel)
    {
        $salesChannel->delete();

        return redirect()
            ->route('sales.channels.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Delete Successfully'));
    }
}
