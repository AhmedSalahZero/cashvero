<?php

use App\Http\Controllers\Analysis\SalesGathering\SalesBreakdownAgainstAnalysisReport;
use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\CashFlowStatementController;
use App\Http\Controllers\DeleteAllRowsFromCaching;
use App\Http\Controllers\DeleteMultiRowsFromCaching;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FilterMainTypeBasedOnDatesController;
use App\Http\Controllers\FinancialStatementController;
use App\Http\Controllers\getUploadPercentage;
use App\Http\Controllers\Helpers\DeleteSingleRecordController;
use App\Http\Controllers\Helpers\EditTableCellsController;
use App\Http\Controllers\Helpers\getEditFormController;
use App\Http\Controllers\Helpers\HelpersController;
use App\Http\Controllers\Helpers\UpdateBasedOnGlobalController;
use App\Http\Controllers\Helpers\UpdateCitiesBasedOnCountryController;
use App\Http\Controllers\IncomeStatementController;
use App\Http\Controllers\RemoveCompanyController;
use App\Http\Controllers\RemoveUsercontroller;
use App\Http\Controllers\RevenueBusinessLineController;
use App\Http\Controllers\RoutesDefinition;
use App\Http\Controllers\SalesGatheringTestController;
use App\Models\Company;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware([])->group(function () {
    // Route::any('FreeUserSubscription', 'UserController@freeSubscription')->name('free.user.subscription');
    Auth::routes();
    
    Route::group(
        [
            'prefix' => LaravelLocalization::setLocale(),
            'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'auth', 'checkIfAccountExpired']
        ],
        function () {
    
            Route::post('remove-user', [RemoveUsercontroller::class, '__invoke'])->name('remove.user');
            Route::post('remove-company', [RemoveCompanyController::class, '__invoke'])->name('remove.company');
            Route::get('/client', function () {
                return view('client_view.supplier_invoices.form');
            });

            Route::resource('section', 'SectionController');
            Route::resource('companySection', 'CompanyController');
            // Route::resource('user', 'UserController');
            Route::get('user/create/{company?}', 'UserController@create')->middleware('isCashManagement')->name('user.create');
            Route::get('user/all/{company?}', 'UserController@index')->middleware('isCashManagement')->name('user.index');
            Route::post('user/{company?}', 'UserController@store')->middleware('isCashManagement')->name('user.store');
            Route::get('user/{user}/edit/{company?}', 'UserController@edit')->middleware('isCashManagement')->name('user.edit');
            Route::put('user/{user}/{company?}', 'UserController@update')->middleware('isCashManagement')->name('user.update');
            Route::delete('user/{user}/{company?}', 'UserController@destroy')->middleware('isCashManagement')->name('user.destroy');
            // Route::resource('toolTipData', 'ToolTipDataController');

            
            
            
            Route::group(['prefix' => 'roles-permissions/', 'as' => 'roles.permissions.'], function () {
                // Route::get('/index/{company?}', 'RolesAndPermissionsController@index')->name('index');
                // Route::get('/create/{company?}', 'RolesAndPermissionsController@create')->name('create');
                // Route::post('/store/{company?}', 'RolesAndPermissionsController@store')->name('store');
                Route::get('/edit/{company?}', 'RolesAndPermissionsController@edit')->middleware(['isCashManagement'])->name('edit');
                Route::post('/update/{company?}', 'RolesAndPermissionsController@update')->name('update');
            });

            Route::get('profile', 'ProfileController@edit')->name('profile.edit');
            Route::put('profile', 'ProfileController@update')->name('profile.update');
            Route::post('toggle-theme', 'ProfileController@toggleTheme')->name('theme.toggle');

            Route::get('update-users-based-on-company-and-role', 'UserController@getUsersBasedOnCompanyAndRole')->name('update.users.based.on.company.and.role');
            Route::get('render-permission-html-for-user', 'UserController@renderPermissionForUser')->name('render.permissions.html.for.user');
            Route::group(['prefix' => 'user-permissions/{user}/', 'as' => 'user.permissions.'], function () {
                Route::get('/index', 'UsersAndPermissionsController@index')->name('index');
                Route::get('/create', 'UsersAndPermissionsController@create')->name('create');
                Route::post('/store', 'UsersAndPermissionsController@store')->name('store');
                Route::get('/edit/{company?}', 'UsersAndPermissionsController@edit')->middleware('isCashManagement')->name('edit');
                Route::post('/update', 'UsersAndPermissionsController@update')->name('update');
            });
            // Route::get('toolTipSectionsFields/{id}', 'ToolTipDataController@sectionFields')->name('section.fields');
            Route::get('logs', 'LogController@show')->name('admin.show.logs');
            Route::get('logs/{user}', 'LogController@showDetail')->name('admin.show.logs.detail');
            //########### Client View ############
            Route::get('/', 'HomeController@index')->name('home');

            Route::prefix('{company}')->group(function () {
                
            
                Route::get('fixed-payments-at-end-and-beginning', 'Loans2Controller@viewFixedAntEndAndBeginning')->name('fixed.loan.fixed.at.end.and.beginning');
                Route::get('variable-payment-loan', 'Loans2Controller@viewVariable')->name('variable.loan');
                Route::resource('/loan2', 'Loans2Controller')->names([
                    'index' => 'loans2.index',
                    'create' => 'loan2.create',
                    'store' => 'loan2.store',
                    'show' => 'loan2.show',
                    'edit' => 'loan2.edit',
                    'update' => 'loan2.update',
                    'destroy' => 'loan2.destroy',
                ])->except('create');
                Route::post('calculate-fixed-at-end-and-beginning', 'Loans2Controller@calculateFixedAtEndAndBeginning')->name('calculate.fixed.at.end.and.beginning');
                Route::post('calculate-variable-at-end-and-beginning', 'Loans2Controller@calculateVariableAtEndAndBeginning')->name('calculate.variable.at.end.and.beginning');
            //   Route::post('save-fixed-at-end', 'SaveFixedAtEndController@__invoke')->name('save.fixed.at.end');
               Route::post('save-loan-dates', 'SaveLoanDatesController@__invoke')->name('save.loan.dates');
                Route::get('fixed-payments-at-end', 'Loans2Controller@create')->name('fixed.loan.fixed.at.end');
                Route::get('calculate-loan-amount', 'Loans2Controller@create')->name('calc.loan.amount');
                Route::get('calculate-interest-rate', 'Loans2Controller@create')->name('calc.interest.percentage');
                Route::get('fixed-payments-at-beginning', 'Loans2Controller@create')->name('fixed.loan.fixed.at.beginning');
                Route::get('variable-payments', 'Loans2Controller@create')->name('variable.payments');
                
                
                //cash vero roles and permissions
                // Route::group(['prefix'=>'cash-vero-permissions'],function(){
                // 	Route::get('create','CashVeroPermissionsController@create')->name('cashvero.permissions.create');
                // 	Route::post('store','CashVeroPermissionsController@store')->name('cashvero.permissions.store');
                // });
            
                    
                Route::get('update-currency-account-based-on-currency/{financialInstitution}', 'UpdateCurrentAccountBasedOnCurrencyController@index')->name('update.current.account.based.on.currency');


                Route::get('checkIfJobFinished/{modelName}', 'SalesGatheringTestController@activeJob')->name('active.job');
                Route::get('filter-column-based-on-another', 'FilterColumnBasedOnAnotherColumnController@filter')->name('filter.column.based.on.another.column');

                Route::get('/redirect', 'HomeController@redirectFun')->name('home.redirect');
                //########### Dashboard ############
                Route::get('/companyGroup', 'HomeController@companyGroup')->name('company.group');
                // Route::any('Admin_Company', 'CompanyController@adminCompany')->name('admin.company');
                // Route::any('Edit_Admin_Company/{companySection}', 'CompanyController@editAdminCompany')->name('edit.admin.company');

                

                //########### Import Routs ############
                // Route::any('inventoryStatementImport', 'InventoryStatementTestController@import')->name('inventoryStatementImport');
                // Route::get('inventoryStatement/insertToMainTable', 'InventoryStatementTestController@insertToMainTable')->name('inventoryStatementTest.insertToMainTable');
                Route::get('salesGatheringImport/{model}/cached-row/{rowId}/edit', 'SalesGatheringTestController@editCachedRow')->name('salesGatheringTest.editCachedRow');
                Route::put('salesGatheringImport/{model}/cached-row/{rowId}', 'SalesGatheringTestController@updateCachedRow')->name('salesGatheringTest.updateCachedRow');
                Route::any('salesGatheringImport/{model}', 'SalesGatheringTestController@import')->name('salesGatheringImport');
                Route::get('SalesGathering/insertToMainTable/{modelName}', 'SalesGatheringTestController@insertToMainTable')->name('salesGatheringTest.insertToMainTable');

                //########### Export Routes ############
                Route::get('salesGathering/export/{model}', 'SalesGatheringController@export')->name('salesGathering.export');
                // type excel or pdf
      
                // ->parameters(['name-of-route'=> inventoryStatement [dependancies injection of model]])

                //########### test table for uploading ############
                // Route::resource('inventoryStatementTest', 'InventoryStatementTestController')
                // 	->only(['edit', 'update', 'destroy']);
                Route::resource('salesGatheringTest', 'SalesGatheringTestController')
                    ->only(['edit', 'update', 'destroy']);

                //########### Sections Resources ############

                // Route::resource('inventoryStatement', 'InventoryStatementController');
                Route::resource('salesGathering', 'SalesGatheringController');

                Route::get('uploading/{model}/{loanId?}', 'SalesGatheringController@index')->name('view.uploading');

                //###########  (TRUNCATE) ############
                Route::get('Truncate/{model}', 'DeletingClass@truncate')->name('truncate');
                Route::delete('DeleteMultipleRows/{model}', 'DeletingClass@multipleRowsDeleting')->name('multipleRowsDelete');
                Route::delete('delete-model', [DeleteSingleRecordController::class, '__invoke'])->name('delete.model');

                //########### Inventory Links ############
                // Route::prefix('/Inventory')->group(function () {
                //     Route::get('/EndBalanceAnalysis/View', 'Analysis\Inventory\EndBalanceAnalysisReport@index')->name('end.balance.analysis');
                //     Route::post('/EndBalanceAnalysis/Result', 'Analysis\Inventory\EndBalanceAnalysisReport@result')->name('end.balance.analysis.result');
                // });

                Route::post('store-new-model', [HelpersController::class, 'storeNewModal'])->name('admin.store.new.modal');
               
                

                // bank certificate of deposit
                Route::middleware('isCashManagement')->group(function () {

                    /**
                 * * Start Of Financial Institution Routes
                 */

                    Route::get('financial-institutions', 'FinancialInstitutionController@index')->name('view.financial.institutions');
                    Route::get('financial-institutions/create/{model?}', 'FinancialInstitutionController@create')->name('create.financial.institutions');
                    Route::post('financial-institutions/create', 'FinancialInstitutionController@store')->name('store.financial.institutions');
                    Route::get('financial-institutions/edit/{financialInstitution}', 'FinancialInstitutionController@edit')->name('edit.financial.institutions');
                    Route::put('financial-institutions/update/{financialInstitution}', 'FinancialInstitutionController@update')->name('update.financial.institutions');
                    Route::delete('financial-institutions/delete/{financialInstitution}', 'FinancialInstitutionController@destroy')->name('delete.financial.institutions');

                    // Route::get('get-financial-institution-accounts-number-based-on-currency/{financialInstitution}/{currency}', 'FinancialInstitutionController@getAccountNumbersBasedOnCurrency');

                    Route::get('financial-institutions', 'FinancialInstitutionController@index')->name('view.financial.institutions');
                    Route::get('financial-institutions/create/{model?}', 'FinancialInstitutionController@create')->name('create.financial.institutions');
                    Route::post('financial-institutions/create', 'FinancialInstitutionController@store')->name('store.financial.institutions');
                    Route::get('financial-institutions/edit/{financialInstitution}', 'FinancialInstitutionController@edit')->name('edit.financial.institutions');
                    Route::put('financial-institutions/update/{financialInstitution}', 'FinancialInstitutionController@update')->name('update.financial.institutions');
                    Route::delete('financial-institutions/delete/{financialInstitution}', 'FinancialInstitutionController@destroy')->name('delete.financial.institutions');

                    Route::get('leasing-companies/create', 'LeasingCompanyController@create')->name('leasing.companies.create');
                    Route::post('leasing-companies/create', 'LeasingCompanyController@store')->name('leasing.companies.store');
                    Route::get('leasing-companies/edit/{leasingCompany}', 'LeasingCompanyController@edit')->name('leasing.companies.edit');
                    Route::put('leasing-companies/update/{leasingCompany}', 'LeasingCompanyController@update')->name('leasing.companies.update');
                    Route::delete('leasing-companies/delete/{leasingCompany}', 'LeasingCompanyController@destroy')->name('leasing.companies.destroy');

                    Route::get('leasing-companies/{leasingCompany}/contracts', 'LeasingContractController@index')->name('leasing.contracts.index');
                    Route::get('leasing-companies/{leasingCompany}/contracts/create', 'LeasingContractController@create')->name('leasing.contracts.create');
                    Route::post('leasing-companies/{leasingCompany}/contracts/store', 'LeasingContractController@store')->name('leasing.contracts.store');
                    Route::get('leasing-companies/{leasingCompany}/contracts/{leasingContract}/edit', 'LeasingContractController@edit')->name('leasing.contracts.edit');
                    Route::put('leasing-companies/{leasingCompany}/contracts/{leasingContract}/update', 'LeasingContractController@update')->name('leasing.contracts.update');
                    Route::delete('leasing-companies/{leasingCompany}/contracts/{leasingContract}/delete', 'LeasingContractController@destroy')->name('leasing.contracts.destroy');

                    Route::get('factoring-companies/create', 'FactoringCompanyController@create')->name('factoring.companies.create');
                    Route::post('factoring-companies/create', 'FactoringCompanyController@store')->name('factoring.companies.store');
                    Route::get('factoring-companies/edit/{factoringCompany}', 'FactoringCompanyController@edit')->name('factoring.companies.edit');
                    Route::put('factoring-companies/update/{factoringCompany}', 'FactoringCompanyController@update')->name('factoring.companies.update');
                    Route::delete('factoring-companies/delete/{factoringCompany}', 'FactoringCompanyController@destroy')->name('factoring.companies.destroy');

                    Route::get('factoring-companies/{factoringCompany}/contracts', 'FactoringContractController@index')->name('factoring.contracts.index');
                    Route::get('factoring-companies/{factoringCompany}/contracts/create', 'FactoringContractController@create')->name('factoring.contracts.create');
                    Route::post('factoring-companies/{factoringCompany}/contracts/store', 'FactoringContractController@store')->name('factoring.contracts.store');
                    Route::get('factoring-companies/{factoringCompany}/contracts/{factoringContract}/edit', 'FactoringContractController@edit')->name('factoring.contracts.edit');
                    Route::put('factoring-companies/{factoringCompany}/contracts/{factoringContract}/update', 'FactoringContractController@update')->name('factoring.contracts.update');
                    Route::delete('factoring-companies/{factoringCompany}/contracts/{factoringContract}/delete', 'FactoringContractController@destroy')->name('factoring.contracts.destroy');

                    Route::get('financial-institutions/{financialInstitution}/add-account', 'FinancialInstitutionController@addAccount')->name('financial.institution.add.account');
                    Route::post('financial-institutions/{financialInstitution}/add-account', 'FinancialInstitutionController@storeAccount')->name('financial.institution.store.account');
                    Route::get('financial-institution-accounts/edit/{financialInstitutionAccount}', 'FinancialInstitutionAccountController@edit')->name('edit.financial.institutions.account');
                    Route::put('financial-institution-accounts/update/{financialInstitution}/{financialInstitutionAccount}', 'FinancialInstitutionAccountController@update')->name('update.financial.institutions.account');
                    Route::delete('financial-institution-accounts/delete/{financialInstitutionAccount}', 'FinancialInstitutionAccountController@destroy')->name('delete.financial.institutions.account');
                    Route::put('financial-institution-accounts/lock-or-unlock/{financialInstitutionAccount}', 'FinancialInstitutionAccountController@lockOrUnlock')->name('lock.or.unlock.financial.institutions.account');
                    Route::put('bank-accounts/lock-or-unlock/{accountType}/{accountId}', 'LockBankAccountController@lockOrUnlock')->name('lock.or.unlock.bank.account');

                    /**
                     * * Bank Accounts
                     * * لعرض الاكونتات الخاصة بالعميل في بنك معين او مؤسسة مالية
                     */
                    Route::get('financial-institutions/{financialInstitution}/bank-accounts', 'FinancialInstitutionController@viewAllAccounts')->name('view.all.bank.accounts');

                    Route::post('add-new-partner', 'AddNewCustomerController@addNew')->name('add.new.partner');
                    Route::post('add-new-partner/{type}', 'AddNewCustomerController@addNew2')->name('add.new.partner.type');
                    Route::resource('opening-balance', 'OpeningBalancesController');
                    Route::resource('customers-opening-balance', 'CustomerOpeningBalancesController');
                    Route::resource('suppliers-opening-balance', 'SupplierOpeningBalancesController');
					Route::get('ajax-refresh-limits-chart', 'CustomerInvoiceDashboardController@refreshBankMovementChart')->name('refresh.chart.limits.data') ; // ajax request
					Route::get('/get-customers-from-currencies/{modelType}', 'AgingController@getCustomersFromBusinessUnitsAndCurrencies')->name('get.customers.or.suppliers.from.business.units.currencies');
                    Route::get('/get-customers-for-settlement-of-opening-balance', 'MoneyReceivedController@getCustomersWithOpeningBalance')->name('get.customers.of.opening-balance');
                    Route::get('/get-suppliers-for-settlement-of-opening-balance', 'MoneyPaymentController@getSuppliersWithOpeningBalance')->name('get.suppliers.of.opening-balance');
					
                 
                    Route::group(['prefix'=>'general-settings'], function () {
                        Route::get('partners', 'PartnersController@index')->name('partners.index');
                        Route::get('partners/create', 'PartnersController@create')->name('partners.create');
                        Route::post('partners/store', 'PartnersController@store')->name('partners.store');
                        Route::get('partners/{partner}/edit', 'PartnersController@edit')->name('partners.edit');
                        Route::put('partners/{partner}/update', 'PartnersController@update')->name('partners.update');
                        Route::delete('partners/{partner}/delete', 'PartnersController@destroy')->name('partners.destroy');
                    
                        Route::get('customers', 'CustomersController@index')->name('customers.index');
                        Route::get('customers/create', 'CustomersController@create')->name('customers.create');
                        Route::post('customers/store', 'CustomersController@store')->name('customers.store');
                        Route::get('customers/{supplier}/edit', 'CustomersController@edit')->name('customers.edit');
                        Route::put('customers/{supplier}/update', 'CustomersController@update')->name('customers.update');
                        Route::delete('customers/{supplier}/delete', 'CustomersController@destroy')->name('customers.destroy');
                    
                    
                        Route::get('suppliers', 'SuppliersController@index')->name('suppliers.index');
                        Route::get('suppliers/create', 'SuppliersController@create')->name('suppliers.create');
                        Route::post('suppliers/store', 'SuppliersController@store')->name('suppliers.store');
                        Route::get('suppliers/{supplier}/edit', 'SuppliersController@edit')->name('suppliers.edit');
                        Route::put('suppliers/{supplier}/update', 'SuppliersController@update')->name('suppliers.update');
                        Route::delete('suppliers/{supplier}/delete', 'SuppliersController@destroy')->name('suppliers.destroy');
                    
                    
                    
                        Route::get('shareholders', 'ShareholdersController@index')->name('shareholders.index');
                        Route::get('shareholders/create', 'ShareholdersController@create')->name('shareholders.create');
                        Route::post('shareholders/store', 'ShareholdersController@store')->name('shareholders.store');
                        Route::get('shareholders/{shareholder}/edit', 'ShareholdersController@edit')->name('shareholders.edit');
                        Route::put('shareholders/{shareholder}/update', 'ShareholdersController@update')->name('shareholders.update');
                        Route::delete('shareholders/{shareholder}/delete', 'ShareholdersController@destroy')->name('shareholders.destroy');
                    
                        Route::get('employees', 'EmployeesController@index')->name('employees.index');
                        Route::get('employees/create', 'EmployeesController@create')->name('employees.create');
                        Route::post('employees/store', 'EmployeesController@store')->name('employees.store');
                        Route::get('employees/{employee}/edit', 'EmployeesController@edit')->name('employees.edit');
                        Route::put('employees/{employee}/update', 'EmployeesController@update')->name('employees.update');
                        Route::delete('employees/{employee}/delete', 'EmployeesController@destroy')->name('employees.destroy');
                    
                        Route::get('subsidiary-companies', 'SubsidiaryCompaniesController@index')->name('subsidiary.companies.index');
                        Route::get('subsidiary-companies/create', 'SubsidiaryCompaniesController@create')->name('subsidiary.companies.create');
                        Route::post('subsidiary-companies/store', 'SubsidiaryCompaniesController@store')->name('subsidiary.companies.store');
                        Route::get('subsidiary-companies/{subsidiaryCompany}/edit', 'SubsidiaryCompaniesController@edit')->name('subsidiary.companies.edit');
                        Route::put('subsidiary-companies/{subsidiaryCompany}/update', 'SubsidiaryCompaniesController@update')->name('subsidiary.companies.update');
                        Route::delete('subsidiary-companies/{subsidiaryCompany}/delete', 'SubsidiaryCompaniesController@destroy')->name('subsidiary.companies.destroy');
                    
                        // Route::get('taxes', 'TaxesController@index')->name('taxes.index');
                        // Route::get('taxes/create', 'TaxesController@create')->name('taxes.create');
                        // Route::post('taxes/store', 'TaxesController@store')->name('taxes.store');
                        // Route::get('taxes/{employee}/edit', 'TaxesController@edit')->name('taxes.edit');
                        // Route::put('taxes/{employee}/update', 'TaxesController@update')->name('taxes.update');
                        // Route::delete('taxes/{employee}/delete', 'TaxesController@destroy')->name('taxes.destroy');
                    
                    
                    
                        Route::get('other-partners', 'OtherPartnersController@index')->name('other.partners.index');
                        Route::get('other-partners/create', 'OtherPartnersController@create')->name('other.partners.create');
                        Route::post('other-partners/store', 'OtherPartnersController@store')->name('other.partners.store');
                        Route::get('other-partners/{otherPartner}/edit', 'OtherPartnersController@edit')->name('other.partners.edit');
                        Route::put('other-partners/{otherPartner}/update', 'OtherPartnersController@update')->name('other.partners.update');
                        Route::delete('other-partners/{otherPartner}/delete', 'OtherPartnersController@destroy')->name('other.partners.destroy');
                    
                        Route::get('business-sectors', 'BusinessSectorsController@index')->name('business.sectors.index');
                        Route::get('business-sectors/create', 'BusinessSectorsController@create')->name('business.sectors.create');
                        Route::post('business-sectors/store', 'BusinessSectorsController@store')->name('business.sectors.store');
                        Route::get('business-sectors/{businessSector}/edit', 'BusinessSectorsController@edit')->name('business.sectors.edit');
                        Route::put('business-sectors/{businessSector}/update', 'BusinessSectorsController@update')->name('business.sectors.update');
                        Route::delete('business-sectors/{businessSector}/delete', 'BusinessSectorsController@destroy')->name('business.sectors.destroy');
                    
                    
                        Route::get('business-units', 'BusinessUnitsController@index')->name('business.units.index');
                        Route::get('business-units/create', 'BusinessUnitsController@create')->name('business.units.create');
                        Route::post('business-units/store', 'BusinessUnitsController@store')->name('business.units.store');
                        Route::get('business-units/{businessUnit}/edit', 'BusinessUnitsController@edit')->name('business.units.edit');
                        Route::put('business-units/{businessUnit}/update', 'BusinessUnitsController@update')->name('business.units.update');
                        Route::delete('business-units/{businessUnit}/delete', 'BusinessUnitsController@destroy')->name('business.units.destroy');
                    
                    
                        Route::get('sales-channels', 'SalesChannelsController@index')->name('sales.channels.index');
                        Route::get('sales-channels/create', 'SalesChannelsController@create')->name('sales.channels.create');
                        Route::post('sales-channels/store', 'SalesChannelsController@store')->name('sales.channels.store');
                        Route::get('sales-channels/{salesChannel}/edit', 'SalesChannelsController@edit')->name('sales.channels.edit');
                        Route::put('sales-channels/{salesChannel}/update', 'SalesChannelsController@update')->name('sales.channels.update');
                        Route::delete('sales-channels/{salesChannel}/delete', 'SalesChannelsController@destroy')->name('sales.channels.destroy');
                    
                    
                    
                                        
                        Route::get('sales-persons', 'SalesPersonsController@index')->name('sales.persons.index');
                        Route::get('sales-persons/create', 'SalesPersonsController@create')->name('sales.persons.create');
                        Route::post('sales-persons/store', 'SalesPersonsController@store')->name('sales.persons.store');
                        Route::get('sales-persons/{salesPerson}/edit', 'SalesPersonsController@edit')->name('sales.persons.edit');
                        Route::put('sales-persons/{salesPerson}/update', 'SalesPersonsController@update')->name('sales.persons.update');
                        Route::delete('sales-persons/{salesPerson}/delete', 'SalesPersonsController@destroy')->name('sales.persons.destroy');
                    
                    
                    
                        Route::get('branches', 'BranchesController@index')->name('branches.index');
                        Route::get('branches/create', 'BranchesController@create')->name('branches.create');
                        Route::post('branches/store', 'BranchesController@store')->name('branches.store');
                        Route::get('branches/{branch}/edit', 'BranchesController@edit')->name('branches.edit');
                        Route::put('branches/{branch}/update', 'BranchesController@update')->name('branches.update');
                        Route::delete('branches/{branch}/delete', 'BranchesController@destroy')->name('branches.destroy');
                        Route::get('get-branches-from-currency', 'BranchesController@getBranchesForCurrency')->name('get.branch.based.on.currency');
                    
                    
                        Route::get('deductions', 'DeductionsController@index')->name('deductions.index');
                        Route::get('deductions/create', 'DeductionsController@create')->name('deductions.create');
                        Route::post('deductions/store', 'DeductionsController@store')->name('deductions.store');
                        Route::get('deductions/{deduction}/edit', 'DeductionsController@edit')->name('deductions.edit');
                        Route::put('deductions/{deduction}/update', 'DeductionsController@update')->name('deductions.update');
                        Route::delete('deductions/{deduction}/delete', 'DeductionsController@destroy')->name('deductions.destroy');
                    
                    
                    });
                 
                 
                 
                 
                    Route::get('lc-settlement-internal-money-transfers', 'LcSettlementInternalMoneyTransferController@index')->name('lc-settlement-internal-money-transfers.index');
                    Route::get('lc-settlement-internal-money-transfers/create', 'LcSettlementInternalMoneyTransferController@create')->name('lc-settlement-internal-money-transfers.create');
                    Route::post('lc-settlement-internal-money-transfers/store', 'LcSettlementInternalMoneyTransferController@store')->name('lc-settlement-internal-money-transfers.store');
                    Route::get('lc-settlement-internal-money-transfers/{lc_settlement_internal_transfer}/edit', 'LcSettlementInternalMoneyTransferController@edit')->name('lc-settlement-internal-money-transfers.edit');
                    Route::put('lc-settlement-internal-money-transfers/{lc_settlement_internal_transfer}/update', 'LcSettlementInternalMoneyTransferController@update')->name('lc-settlement-internal-money-transfers.update');
                    Route::delete('lc-settlement-internal-money-transfers/{lc_settlement_internal_transfer}/delete', 'LcSettlementInternalMoneyTransferController@destroy')->name('lc-settlement-internal-money-transfers.destroy');
                 
                 
                 
                 
                 
                 
                 
                 
                    Route::get('internal-money-transfers', 'InternalMoneyTransferController@index')->name('internal-money-transfers.index');
                    Route::get('internal-money-transfers/{type}/create', 'InternalMoneyTransferController@create')->name('internal-money-transfers.create');
                    Route::post('internal-money-transfers/{type}/store', 'InternalMoneyTransferController@store')->name('internal-money-transfers.store');
                    Route::get('internal-money-transfers/{type}/{internal_money_transfer}/edit', 'InternalMoneyTransferController@edit')->name('internal-money-transfers.edit');
                    Route::put('internal-money-transfers/{type}/{internal_money_transfer}/update', 'InternalMoneyTransferController@update')->name('internal-money-transfers.update');
                    Route::delete('internal-money-transfers/{type}/{internal_money_transfer}/delete', 'InternalMoneyTransferController@destroy')->name('internal-money-transfers.destroy');
                 
                 
                    Route::get('buy-or-sell-currencies', 'BuyOrSellCurrenciesController@index')->name('buy-or-sell-currencies.index');
                    Route::get('buy-or-sell-currencies/create', 'BuyOrSellCurrenciesController@create')->name('buy-or-sell-currencies.create');
                    Route::post('buy-or-sell-currencies/store', 'BuyOrSellCurrenciesController@store')->name('buy-or-sell-currencies.store');
                    Route::get('buy-or-sell-currencies/{buy_or_sell_currency}/edit', 'BuyOrSellCurrenciesController@edit')->name('buy-or-sell-currencies.edit');
                    Route::put('buy-or-sell-currencies/{buy_or_sell_currency}/update', 'BuyOrSellCurrenciesController@update')->name('buy-or-sell-currencies.update');
                    Route::delete('buy-or-sell-currencies/{buy_or_sell_currency}/delete', 'BuyOrSellCurrenciesController@destroy')->name('buy-or-sell-currencies.destroy');
                 
                 
                 
        
                 
                 
                    //  Route::get('internal-money-');
                 
                    //  Route::resource('contracts', 'ContractsController');
                    Route::post('store-po-allocation', 'ContractsController@storePoAllocations')->name('store.po.allocations');
                    Route::get('contracts/{type}', 'ContractsController@index')->name('contracts.index');
                    Route::get('contracts/create/{type}', 'ContractsController@create')->name('contracts.create');
                    Route::post('contracts/{type}', 'ContractsController@store')->name('contracts.store');
                    Route::get('contracts/{contract}/edit/{type}', 'ContractsController@edit')->name('contracts.edit');
                    Route::put('contracts/{contract}/{type}', 'ContractsController@update')->name('contracts.update');
                    Route::delete('contracts/{contract}/{type}', 'ContractsController@destroy')->name('contracts.destroy');
                    Route::get('get-contracts-for-customer-or-supplier', 'ContractsController@getContractsForCustomerOrSupplier')->name('get.contracts.for.customer.or.supplier');
                    Route::get('generate-contract-code/{type}', 'ContractsController@generateRandomCode')->name('generate.unique.rondom.contract.code');
                    Route::get('financial-institutions/js-update-contracts-based-on-customer', 'ContractsController@updateContractsBasedOnCustomer')->name('update.contracts.based.on.customer');
                    Route::get('financial-institutions/js-update-sales-orders-based-on-contract', 'ContractsController@updateSalesOrdersBasedOnContract')->name('update.sales.orders.based.on.contract');
                    Route::get('financial-institutions/js-update-purchase-orders-based-on-contract', 'ContractsController@updatePurchaseOrdersBasedOnContract')->name('update.purchase.orders.based.on.contract');
                    Route::get('financial-institutions/get-lc-issuance-based-of-financial-institution', 'FinancialInstitutionController@getLcIssuanceBasedOnFinancialInstitution')->name('update.lc.issuance.based.on.financial.institution');
                 
                 
                    //
                 
                    Route::get('expense-category', 'CashExpenseCategoryController@index')->name('cash.expense.category.index');
                    Route::get('expense-category/create', 'CashExpenseCategoryController@create')->name('cash.expense.category.create');
                    Route::post('expense-category', 'CashExpenseCategoryController@store')->name('cash.expense.category.store');
                    Route::get('expense-category/{cashExpenseCategory}/edit', 'CashExpenseCategoryController@edit')->name('cash.expense.category.edit');
                    Route::put('expense-category/{cashExpenseCategory}', 'CashExpenseCategoryController@update')->name('cash.expense.category.update');
                    Route::delete('expense-category/{cashExpenseCategory}', 'CashExpenseCategoryController@destroy')->name('cash.expense.category.destroy');
                    Route::get('update-expense-category-name-based-on-expense-category-category', 'CashExpenseCategoryController@updateExpenseCategoryNameBasedOnCategory')->name('update.expense.category.name.based.on.category');
                    //
                    Route::get('notifications/{type}', 'NotificationsController@index')->name('view.notifications');
                    Route::resource('notifications-settings', 'NotificationSettingsController');
                    Route::resource('odoo-settings', 'OdooSettingController');
                    Route::get('mark-notifications-as-read', 'NotificationSettingsController@markAsRead')->name('mark.notifications.as.read');

                    Route::get('adjust-due-dates/{modelId}/{modelType}', 'AdjustedDueDateHistoriesController@index')->name('adjust.due.dates');
                    Route::post('adjust-due-dates/{modelId}/{modelType}', 'AdjustedDueDateHistoriesController@store')->name('store.adjust.due.dates');
                    Route::get('adjust-due-dates/edit/{modelId}/{modelType}/{dueDateHistory}', 'AdjustedDueDateHistoriesController@edit')->name('edit.adjust.due.dates');
                    Route::patch('adjust-due-dates/edit/{modelId}/{modelType}/{dueDateHistory}', 'AdjustedDueDateHistoriesController@update')->name('update.adjust.due.dates');
                    Route::delete('delete-adjust-due-dates/edit/{modelId}/{modelType}/{dueDateHistory}', 'AdjustedDueDateHistoriesController@destroy')->name('delete.adjust.due.dates');
                 
                 
                 
                    //  Route::get('adjust-due-dates/{modelId}/{modelType}', 'AdjustedDueDateHistoriesController@index')->name('adjust.due.dates');
                    //  Route::post('adjust-due-dates/{modelId}/{modelType}', 'AdjustedDueDateHistoriesController@store')->name('store.adjust.due.dates');
                    //  Route::get('adjust-due-dates/edit/{modelId}/{modelType}/{dueDateHistory}', 'AdjustedDueDateHistoriesController@edit')->name('edit.adjust.due.dates');
                    Route::patch('invoice-deductions/edit/{modelId}/{modelType}', 'InvoiceDeductionsController@update')->name('update.invoice.deductions');
                    //  Route::delete('delete-invoice-deductions/edit/{modelId}/{modelType}', 'InvoiceDeductionsController@destroy')->name('delete.invoice.deductions');
                 

                 

                    Route::get('foreign-exchange-rate', 'ForeignExchangeRateController@index')->name('view.foreign.exchange.rate');
                    Route::post('foreign-exchange-rate', 'ForeignExchangeRateController@store')->name('store.foreign.exchange.rate');
                    Route::get('foreign-exchange-rate/edit/{foreignExchangeRate}', 'ForeignExchangeRateController@edit')->name('edit.foreign.exchange.rate');
                    Route::patch('foreign-exchange-rate/edit/{foreignExchangeRate}', 'ForeignExchangeRateController@update')->name('update.foreign.exchange.rate');
                    Route::delete('delete-foreign-exchange-rate/edit/{foreignExchangeRate}', 'ForeignExchangeRateController@destroy')->name('delete.foreign.exchange.rate');



                    Route::get('financial-institutions/{financialInstitution}/full-secured-overdraft', 'FullySecuredOverdraftController@index')->name('view.fully.secured.overdraft');
                    Route::get('financial-institutions/{financialInstitution}/full-secured-overdraft/create', 'FullySecuredOverdraftController@create')->name('create.fully.secured.overdraft');
                    Route::post('financial-institutions/{financialInstitution}/full-secured-overdraft/create', 'FullySecuredOverdraftController@store')->name('store.fully.secured.overdraft');
                    Route::get('financial-institutions/{financialInstitution}/full-secured-overdraft/edit/{fullySecuredOverdraft}', 'FullySecuredOverdraftController@edit')->name('edit.fully.secured.overdraft');
                    Route::put('financial-institutions/{financialInstitution}/full-secured-overdraft/update/{fullySecuredOverdraft}', 'FullySecuredOverdraftController@update')->name('update.fully.secured.overdraft');
                    Route::delete('financial-institutions/{financialInstitution}/full-secured-overdraft/delete/{fullySecuredOverdraft}', 'FullySecuredOverdraftController@destroy')->name('delete.fully.secured.overdraft');
                
                    Route::post('financial-institutions/{financialInstitution}/fully-secured-overdraft/apply-rate/{fullySecuredOverdraft}', 'FullySecuredOverdraftController@applyRate')->name('fully-secured-overdraft-apply.rates');
                    Route::post('financial-institutions/{financialInstitution}/fully-secured-overdraft/edit-rates/{rate}', 'FullySecuredOverdraftController@editRate')->name('fully-secured-overdraft-edit-rates');
                    Route::get('financial-institutions/{financialInstitution}/fully-secured-overdraft/delete-rates/{rate}', 'FullySecuredOverdraftController@deleteRate')->name('fully-secured-overdraft-delete-rate');
                 
                 
                 
                 
                    Route::get('financial-institutions/{financialInstitution}/clean-overdraft', 'CleanOverdraftController@index')->name('view.clean.overdraft');
                    Route::get('financial-institutions/{financialInstitution}/clean-overdraft/create', 'CleanOverdraftController@create')->name('create.clean.overdraft');
                    Route::post('financial-institutions/{financialInstitution}/clean-overdraft/create', 'CleanOverdraftController@store')->name('store.clean.overdraft');
                    Route::get('financial-institutions/{financialInstitution}/clean-overdraft/edit/{cleanOverdraft}', 'CleanOverdraftController@edit')->name('edit.clean.overdraft');
                    Route::put('financial-institutions/{financialInstitution}/clean-overdraft/update/{cleanOverdraft}', 'CleanOverdraftController@update')->name('update.clean.overdraft');
                    Route::delete('financial-institutions/{financialInstitution}/clean-overdraft/delete/{cleanOverdraft}', 'CleanOverdraftController@destroy')->name('delete.clean.overdraft');
                    Route::post('financial-institutions/{financialInstitution}/clean-overdraft/apply-rate/{cleanOverdraft}', 'CleanOverdraftController@applyRate')->name('clean-overdraft-apply.rates');
                    Route::post('financial-institutions/{financialInstitution}/clean-overdraft/edit-rates/{rate}', 'CleanOverdraftController@editRate')->name('clean-overdraft-edit-rates');
                    Route::get('financial-institutions/{financialInstitution}/clean-overdraft/delete-rates/{rate}', 'CleanOverdraftController@deleteRate')->name('clean-overdraft-delete-rate');
                 
					Route::any('salesGatheringImport/last-upload-failed/{model}', 'SalesGatheringTestController@lastUploadFailed')->name('last.upload.failed');

                    Route::get('financial-institutions/{financialInstitution}/medium-term-loan', 'MediumTermLoanController@index')->name('loans.index');
                    Route::get('financial-institutions/{financialInstitution}/medium-term-loan/create', 'MediumTermLoanController@create')->name('loans.create');
                    Route::post('financial-institutions/{financialInstitution}/medium-term-loan/store', 'MediumTermLoanController@store')->name('loans.store');
                    Route::get('financial-institutions/{financialInstitution}/medium-term-loan/{mediumTermLoan}/edit', 'MediumTermLoanController@edit')->name('loans.edit');
                    Route::put('financial-institutions/{financialInstitution}/medium-term-loan/{mediumTermLoan}/update', 'MediumTermLoanController@update')->name('loans.update');
                    Route::delete('financial-institutions/{financialInstitution}/medium-term-loan/{mediumTermLoan}/delete', 'MediumTermLoanController@destroy')->name('loans.destroy');
                 
                 
                
                 
                 
                    Route::get('loan-schedule-settlement/{loanSchedule}', 'MediumTermLoanController@viewLoanScheduleSettlement')->name('view.loan.schedule.settlements');
                    Route::post('loan-schedule-settlements/{loanSchedule}', 'MediumTermLoanController@storeLoanScheduleSettlement')->name('store.loan.schedule.settlements');
                    Route::get('edit-loan-schedule-settlement/{loanScheduleSettlement}', 'MediumTermLoanController@editLoanScheduleSettlement')->name('edit.loan.schedule.settlements');
                    Route::patch('loan-schedule-settlements/{loanScheduleSettlement}', 'MediumTermLoanController@updateLoanScheduleSettlement')->name('update.loan.schedule.settlements');
                    Route::delete('delete-loan-schedule-settlement/{loanScheduleSettlement}', 'MediumTermLoanController@deleteLoanScheduleSettlement')->name('delete.loan.schedule.settlements');

                    Route::get('contract-loan-schedule/account-numbers', 'ContractLoanScheduleController@getAccountNumbersForDraweeBank')->name('contract.loan.schedule.account.numbers');
                    Route::get('contract-loan-schedule-settlement/{contractLoanSchedule}', 'ContractLoanScheduleController@viewSettlement')->name('view.contract.loan.schedule.settlements');
                    Route::post('contract-loan-schedule-settlements/{contractLoanSchedule}', 'ContractLoanScheduleController@storeSettlement')->name('store.contract.loan.schedule.settlements');
                    Route::get('edit-contract-loan-schedule-settlement/{contractLoanScheduleSettlement}', 'ContractLoanScheduleController@editSettlement')->name('edit.contract.loan.schedule.settlements');
                    Route::patch('contract-loan-schedule-settlements/{contractLoanScheduleSettlement}', 'ContractLoanScheduleController@updateSettlement')->name('update.contract.loan.schedule.settlements');
                    Route::delete('delete-contract-loan-schedule-settlement/{contractLoanScheduleSettlement}', 'ContractLoanScheduleController@deleteSettlement')->name('delete.contract.loan.schedule.settlements');

                    Route::get('medium-term-loan-report', 'MediumTermLoanController@refreshReport')->name('refresh.medium.term.loan.report'); // ajax
                    Route::get('get-medium-term-loan-for-financial-institution', 'MediumTermLoanController@getMediumTermLoanForFinancialInstitution')->name('get.medium.term.loan.for.financial.institution');
                 

                    Route::get('financial-institutions/{financialInstitution}/overdraft-against-commercial-paper', 'OverdraftAgainstCommercialPaperController@index')->name('view.overdraft.against.commercial.paper');
                    Route::get('financial-institutions/{financialInstitution}/overdraft-against-commercial-paper/create', 'OverdraftAgainstCommercialPaperController@create')->name('create.overdraft.against.commercial.paper');
                    Route::post('financial-institutions/{financialInstitution}/overdraft-against-commercial-paper/create', 'OverdraftAgainstCommercialPaperController@store')->name('store.overdraft.against.commercial.paper');
                    Route::get('financial-institutions/{financialInstitution}/overdraft-against-commercial-paper/edit/{overdraftAgainstCommercialPaper}', 'OverdraftAgainstCommercialPaperController@edit')->name('edit.overdraft.against.commercial.paper');
                    Route::put('financial-institutions/{financialInstitution}/overdraft-against-commercial-paper/update/{overdraftAgainstCommercialPaper}', 'OverdraftAgainstCommercialPaperController@update')->name('update.overdraft.against.commercial.paper');
                    Route::delete('financial-institutions/{financialInstitution}/overdraft-against-commercial-paper/delete/{overdraftAgainstCommercialPaper}', 'OverdraftAgainstCommercialPaperController@destroy')->name('delete.overdraft.against.commercial.paper');
                 
                    Route::post('financial-institutions/{financialInstitution}/overdraft-against-commercial-paper/apply-rate/{overdraftAgainstCommercialPaper}', 'OverdraftAgainstCommercialPaperController@applyRate')->name('overdraft-against-commercial-paper-apply.rates');
                    Route::post('financial-institutions/{financialInstitution}/overdraft-against-commercial-paper/edit-rates/{rate}', 'OverdraftAgainstCommercialPaperController@editRate')->name('overdraft-against-commercial-paper-edit-rates');
                    Route::get('financial-institutions/{financialInstitution}/overdraft-against-commercial-paper/delete-rates/{rate}', 'OverdraftAgainstCommercialPaperController@deleteRate')->name('overdraft-against-commercial-paper-delete-rate');
                 
                 
                 
                    Route::get('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract', 'OverdraftAgainstAssignmentOfContractController@index')->name('view.overdraft.against.assignment.of.contract');
                    Route::get('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/create', 'OverdraftAgainstAssignmentOfContractController@create')->name('create.overdraft.against.assignment.of.contract');
                    Route::post('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/create', 'OverdraftAgainstAssignmentOfContractController@store')->name('store.overdraft.against.assignment.of.contract');
                    Route::get('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/edit/{odAgainstAssignmentOfContract}', 'OverdraftAgainstAssignmentOfContractController@edit')->name('edit.overdraft.against.assignment.of.contract');
                    Route::put('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/update/{odAgainstAssignmentOfContract}', 'OverdraftAgainstAssignmentOfContractController@update')->name('update.overdraft.against.assignment.of.contract');
                    Route::delete('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/delete/{odAgainstAssignmentOfContract}', 'OverdraftAgainstAssignmentOfContractController@destroy')->name('delete.overdraft.against.assignment.of.contract');
                 
                    Route::post('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/lending-information/{odAgainstAssignmentOfContract}', 'OverdraftAgainstAssignmentOfContractController@applyLendingInformation')->name('lending.information.apply.for.against.assignment.of.contract');
                    Route::post('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/edit-lending-information/{lendingInformation}', 'OverdraftAgainstAssignmentOfContractController@editLendingInformation')->name('lending.information.edit.for.against.assignment.of.contract');
                    Route::get('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/delete-lending-information/{lendingInformation}', 'OverdraftAgainstAssignmentOfContractController@deleteLendingInformation')->name('lending.information.delete.for.against.assignment.of.contract');
                    Route::get('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/apply-against-lending/{lendingInformation}', 'OverdraftAgainstAssignmentOfContractController@applyAgainstLending')->name('apply.against.lending');
                    Route::put('contract/{contract}/{type}/mark-as-finished', 'ContractsController@markAsFinished')->name('contract.mark.as.finished');
                    Route::put('contract/{contract}/{type}/mark-as-running-and-against', 'ContractsController@markAsRunningAndAgainst')->name('contract.mark.as.running.and.against');
                 
                 
                    Route::post('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/apply-rate/{odAgainstAssignmentOfContract}', 'OverdraftAgainstAssignmentOfContractController@applyRate')->name('overdraft-against-assignment-of-contract-apply.rates');
                    Route::post('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/edit-rates/{rate}', 'OverdraftAgainstAssignmentOfContractController@editRate')->name('overdraft-against-assignment-of-contract-edit-rates');
                    Route::get('financial-institutions/{financialInstitution}/overdraft-against-assignment-of-contract/delete-rates/{rate}', 'OverdraftAgainstAssignmentOfContractController@deleteRate')->name('overdraft-against-assignment-of-contract-delete-rate');
                 
                    /**
                     * * start certificates of deposit
                     */

                    Route::get('financial-institutions/{financialInstitution}/certificates-of-deposit', 'CertificatesOfDepositsController@index')->name('view.certificates.of.deposit');
                    Route::get('financial-institutions/{financialInstitution}/certificates-of-deposit/create', 'CertificatesOfDepositsController@create')->name('create.certificates.of.deposit');
                    Route::post('financial-institutions/{financialInstitution}/certificates-of-deposit/create', 'CertificatesOfDepositsController@store')->name('store.certificates.of.deposit');
                    Route::get('financial-institutions/{financialInstitution}/certificates-of-deposit/edit/{certificatesOfDeposit}', 'CertificatesOfDepositsController@edit')->name('edit.certificates.of.deposit');
                    Route::put('financial-institutions/{financialInstitution}/certificates-of-deposit/update/{certificatesOfDeposit}', 'CertificatesOfDepositsController@update')->name('update.certificates.of.deposit');
                    Route::delete('financial-institutions/{financialInstitution}/certificates-of-deposit/delete/{certificatesOfDeposit}', 'CertificatesOfDepositsController@destroy')->name('delete.certificates.of.deposit');
                    Route::post('financial-institutions/{financialInstitution}/certificates-of-deposit/apply-deposit/{certificatesOfDeposit}', 'CertificatesOfDepositsController@applyDeposit')->name('apply.deposit.to.certificate.of.deposit');
                    Route::post('financial-institutions/{financialInstitution}/certificates-of-deposit/apply-break/{certificatesOfDeposit}', 'CertificatesOfDepositsController@applyBreak')->name('apply.break.to.certificate.of.deposit');
                    Route::post('financial-institutions/{financialInstitution}/certificates-of-deposit/reverse-deposit/{certificatesOfDeposit}', 'CertificatesOfDepositsController@reverseDeposit')->name('reverse.deposit.to.certificate.of.deposit');
                    Route::post('financial-institutions/{financialInstitution}/certificates-of-deposit/reverse-broken/{certificatesOfDeposit}', 'CertificatesOfDepositsController@reverseBroken')->name('reverse.broken.to.certificate.of.deposit');
                    
                    Route::post('financial-institutions/{financialInstitution}/certificates-of-deposit/apply-period-interest/{certificatesOfDeposit}', 'CertificatesOfDepositsController@applyPeriodInterest')->name('apply.period.interest.to.certificates.of.deposit');
                    Route::get('financial-institutions/{financialInstitution}/certificates-of-deposit/view-period-interests/{certificatesOfDeposit}', 'CertificatesOfDepositsController@viewPeriodInterest')->name('view.period.interest.to.certificates.of.deposit');
                    Route::delete('financial-institutions/{financialInstitution}/certificates-of-deposit/delete-period-interests/{certificatesOfDeposit}/{currentAccountBankStatement}', 'CertificatesOfDepositsController@deletePeriodInterest')->name('delete.period.interest.to.certificates.of.deposit');
                     
                    /**
                     * * end certificates of deposit
                     */





                    /**
                     * * start time of deposit
                     */

                    Route::get('financial-institutions/{financialInstitution}/time-of-deposit', 'TimeOfDepositsController@index')->name('view.time.of.deposit');
                    Route::get('financial-institutions/{financialInstitution}/time-of-deposit/create', 'TimeOfDepositsController@create')->name('create.time.of.deposit');
                    Route::post('financial-institutions/{financialInstitution}/time-of-deposit/create', 'TimeOfDepositsController@store')->name('store.time.of.deposit');
                    Route::get('financial-institutions/{financialInstitution}/time-of-deposit/edit/{timeOfDeposit}', 'TimeOfDepositsController@edit')->name('edit.time.of.deposit');
                    Route::put('financial-institutions/{financialInstitution}/time-of-deposit/update/{timeOfDeposit}', 'TimeOfDepositsController@update')->name('update.time.of.deposit');
                    Route::delete('financial-institutions/{financialInstitution}/time-of-deposit/delete/{timeOfDeposit}', 'TimeOfDepositsController@destroy')->name('delete.time.of.deposit');
                    Route::post('financial-institutions/{financialInstitution}/time-of-deposit/apply-deposit/{timeOfDeposit}', 'TimeOfDepositsController@applyDeposit')->name('apply.deposit.to.time.of.deposit');
                    Route::post('financial-institutions/{financialInstitution}/time-of-deposit/apply-period-interest/{timeOfDeposit}', 'TimeOfDepositsController@applyPeriodInterest')->name('apply.period.interest.to.time.of.deposit');
                    Route::get('financial-institutions/{financialInstitution}/time-of-deposit/view-period-interests/{timeOfDeposit}', 'TimeOfDepositsController@viewPeriodInterest')->name('view.period.interest.to.time.of.deposit');
                    Route::delete('financial-institutions/{financialInstitution}/time-of-deposit/delete-period-interests/{timeOfDeposit}/{currentAccountBankStatement}', 'TimeOfDepositsController@deletePeriodInterest')->name('delete.period.interest.to.time.of.deposit');
                    Route::post('financial-institutions/{financialInstitution}/time-of-deposit/apply-break/{timeOfDeposit}', 'TimeOfDepositsController@applyBreak')->name('apply.break.to.time.of.deposit');
                    Route::post('financial-institutions/{financialInstitution}/time-of-deposit/reverse-deposit/{timeOfDeposit}', 'TimeOfDepositsController@reverseDeposit')->name('reverse.deposit.to.time.of.deposit');
                    Route::post('financial-institutions/{financialInstitution}/time-of-deposit/reverse-broken/{timeOfDeposit}', 'TimeOfDepositsController@reverseBroken')->name('reverse.broken.to.time.of.deposit');

                     
                     
                     
                    Route::get('time-of-deposit-renewal-date/{timeOfDeposit}', 'TimeOfDepositRenewalDateController@index')->name('time.of.deposit.renewal.date');
                    Route::post('time-of-deposit-renewal-date/{timeOfDeposit}', 'TimeOfDepositRenewalDateController@store')->name('store.time.of.deposit.renewal.date');
                    Route::get('time-of-deposit-renewal-date/edit/{timeOfDeposit}/{TdRenewalDateHistory}', 'TimeOfDepositRenewalDateController@edit')->name('edit.time.of.deposit.renewal.date');
                    Route::patch('time-of-deposit-renewal-date/edit/{timeOfDeposit}/{TdRenewalDateHistory}', 'TimeOfDepositRenewalDateController@update')->name('update.time.of.deposit.renewal.date');
                    Route::delete('delete-time-of-deposit-renewal-date/{timeOfDeposit}/{TdRenewalDateHistory}', 'TimeOfDepositRenewalDateController@destroy')->name('delete.time.of.deposit.renewal.date');
                     
                     
                    /**
                     * * end time of deposit
                     */




                    Route::get('financial-institutions/{financialInstitution}/letter-of-guarantee-facility', 'LetterOfGuaranteeFacilityController@index')->name('view.letter.of.guarantee.facility');
                    Route::get('financial-institutions/{financialInstitution}/letter-of-guarantee-facility/create', 'LetterOfGuaranteeFacilityController@create')->name('create.letter.of.guarantee.facility');
                    Route::post('financial-institutions/{financialInstitution}/letter-of-guarantee-facility/create', 'LetterOfGuaranteeFacilityController@store')->name('store.letter.of.guarantee.facility');
                    Route::get('financial-institutions/{financialInstitution}/letter-of-guarantee-facility/edit/{letterOfGuaranteeFacility}', 'LetterOfGuaranteeFacilityController@edit')->name('edit.letter.of.guarantee.facility');
                    Route::put('financial-institutions/{financialInstitution}/letter-of-guarantee-facility/update/{letterOfGuaranteeFacility}', 'LetterOfGuaranteeFacilityController@update')->name('update.letter.of.guarantee.facility');
                    Route::delete('financial-institutions/{financialInstitution}/letter-of-guarantee-facility/delete/{letterOfGuaranteeFacility}', 'LetterOfGuaranteeFacilityController@destroy')->name('delete.letter.of.guarantee.facility');
                    Route::get('financial-institutions/update-outstanding-balance-and-limits', 'LetterOfGuaranteeFacilityController@updateOutstandingBalanceAndLimits')->name('update.letter.of.guarantee.outstanding.balance.and.limit');
                    Route::get('get-lg-facility-based-on-financial-institution', 'LetterOfGuaranteeFacilityController@getLgFacilityBasedOnFinancialInstitution')->name('get.lg.facility.based.on.financial.institution');
                    Route::get('letter-of-guarantee-issuance', 'LetterOfGuaranteeIssuanceController@index')->name('view.letter.of.guarantee.issuance');
                    Route::get('letter-of-guarantee-issuance/create/{source}', 'LetterOfGuaranteeIssuanceController@create')->name('create.letter.of.guarantee.issuance');
                    Route::post('letter-of-guarantee-issuance/create/{source}', 'LetterOfGuaranteeIssuanceController@store')->name('store.letter.of.guarantee.issuance');
                    Route::get('letter-of-guarantee-issuance/edit/{letterOfGuaranteeIssuance}/{source}', 'LetterOfGuaranteeIssuanceController@edit')->name('edit.letter.of.guarantee.issuance');
                    Route::put('letter-of-guarantee-issuance/update/{letterOfGuaranteeIssuance}/{source}', 'LetterOfGuaranteeIssuanceController@update')->name('update.letter.of.guarantee.issuance');
                    Route::delete('letter-of-guarantee-issuance/delete/{letterOfGuaranteeIssuance}/{source}', 'LetterOfGuaranteeIssuanceController@destroy')->name('delete.letter.of.guarantee.issuance');
                    Route::post('letter-of-guarantee-issuance/cancel/{letterOfGuaranteeIssuance}/{source}', 'LetterOfGuaranteeIssuanceController@cancel')->name('cancel.letter.of.guarantee.issuance');
                    Route::post('letter-of-guarantee-issuance/apply-amount-to-be-decreased/{letterOfGuaranteeIssuance}/{source}', 'LetterOfGuaranteeIssuanceController@applyAmountToBeDecreased')->name('advanced.lg.payment.apply.amount.to.be.decreased');
                    Route::post('letter-of-guarantee-issuance/edit-amount-to-be-decreased/{lgAdvancedPaymentHistory}/{source}', 'LetterOfGuaranteeIssuanceController@editAmountToBeDecreased')->name('advanced.lg.payment.edit.amount.to.be.decreased');
                    Route::get('letter-of-guarantee-issuance/delete-advanced-payment/{lgAdvancedPaymentHistory}', 'LetterOfGuaranteeIssuanceController@deleteAdvancedPayment')->name('delete.lg.advanced.payment');
                    Route::post('letter-of-guarantee-issuance/back-to-running/{letterOfGuaranteeIssuance}/{source}', 'LetterOfGuaranteeIssuanceController@backToRunningStatus')->name('back.to.running.letter.of.guarantee.issuance');
                    Route::get('letter-of-guarantee-issuance/template/{source}', 'LgIssuanceImportController@downloadTemplate')->name('download.letter.of.guarantee.issuance.template');
                    Route::post('letter-of-guarantee-issuance/import/{source}', 'LgIssuanceImportController@upload')->name('import.letter.of.guarantee.issuance');
                    Route::get('letter-of-guarantee-issuance/import-status/{importRun}', 'LgIssuanceImportController@status')->name('status.letter.of.guarantee.issuance.import');
                    Route::get('letter-of-guarantee-issuance/import-errors/{importRun}', 'LgIssuanceImportController@errors')->name('errors.letter.of.guarantee.issuance.import');
                    
                                     
                    Route::get('letter-of-guarantee-issuance-renewal-date/{letterOfGuaranteeIssuance}', 'LetterOfGuaranteeIssuanceRenewalDateController@index')->name('letter.of.issuance.renewal.date');
                    Route::post('letter-of-guarantee-issuance-renewal-date/{letterOfGuaranteeIssuance}', 'LetterOfGuaranteeIssuanceRenewalDateController@store')->name('store.letter.of.issuance.renewal.date');
                    Route::get('letter-of-guarantee-issuance-renewal-date/edit/{letterOfGuaranteeIssuance}/{LgRenewalDateHistory}', 'LetterOfGuaranteeIssuanceRenewalDateController@edit')->name('edit.letter.of.issuance.renewal.date');
                    Route::patch('letter-of-guarantee-issuance-renewal-date/edit/{letterOfGuaranteeIssuance}/{LgRenewalDateHistory}', 'LetterOfGuaranteeIssuanceRenewalDateController@update')->name('update.letter.of.issuance.renewal.date');
                    Route::delete('delete-letter-of-guarantee-issuance-renewal-date/{letterOfGuaranteeIssuance}/{LgRenewalDateHistory}', 'LetterOfGuaranteeIssuanceRenewalDateController@destroy')->name('delete.letter.of.issuance.renewal.date');
                 
                
                    
                    // letter of credit issuance
                    
                    Route::get('letter-of-credit-issuance', 'LetterOfCreditIssuanceController@index')->name('view.letter.of.credit.issuance');
                    Route::get('letter-of-credit-issuance/create/{source}', 'LetterOfCreditIssuanceController@create')->name('create.letter.of.credit.issuance');
                    Route::post('letter-of-credit-issuance/create/{source}', 'LetterOfCreditIssuanceController@store')->name('store.letter.of.credit.issuance');
                    Route::get('letter-of-credit-issuance/edit/{letterOfCreditIssuance}/{source}', 'LetterOfCreditIssuanceController@edit')->name('edit.letter.of.credit.issuance');
                    Route::put('letter-of-credit-issuance/update/{letterOfCreditIssuance}/{source}', 'LetterOfCreditIssuanceController@update')->name('update.letter.of.credit.issuance');
                    Route::delete('letter-of-credit-issuance/delete/{letterOfCreditIssuance}/{source}', 'LetterOfCreditIssuanceController@destroy')->name('delete.letter.of.credit.issuance');
                    Route::post('letter-of-credit-issuance/cancel/{letterOfCreditIssuance}/{source}', 'LetterOfCreditIssuanceController@markAsPaid')->name('make.letter.of.credit.issuance.as.paid');
                    // Route::post('letter-of-credit-issuance/apply-amount-to-be-decreased/{letterOfCreditIssuance}/{source}', 'LetterOfCreditIssuanceController@applyAmountToBeDecreased')->name('advanced.lc.payment.apply.amount.to.be.decreased');
                    // Route::post('letter-of-credit-issuance/edit-amount-to-be-decreased/{lcAdvancedPaymentHistory}/{source}', 'LetterOfCreditIssuanceController@editAmountToBeDecreased')->name('advanced.lc.payment.edit.amount.to.be.decreased');
                    // Route::get('letter-of-credit-issuance/delete-advanced-payment/{lcAdvancedPaymentHistory}', 'LetterOfCreditIssuanceController@deleteAdvancedPayment')->name('delete.lc.advanced.payment');
                    Route::post('letter-of-credit-issuance/back-to-running/{letterOfCreditIssuance}/{source}', 'LetterOfCreditIssuanceController@backToRunningStatus')->name('back.to.running.letter.of.credit.issuance');
                    Route::get('financial-institutions/update-outstanding-balance-and-limits-for-lc', 'LetterOfCreditFacilityController@updateOutstandingBalanceAndLimits')->name('update.letter.of.credit.outstanding.balance.and.limit');
                    Route::get('get-lc-facility-based-on-financial-institution', 'LetterOfCreditFacilityController@getLcFacilityBasedOnFinancialInstitution')->name('get.lc.facility.based.on.financial.institution');

                    Route::post('letter-of-credit-issuance/apply-expense/{letterOfCreditIssuance}', 'LetterOfCreditIssuanceController@applyExpense')->name('apply.lc.issuance.expense');
                    Route::post('letter-of-credit-issuance/update-expense/{expense}', 'LetterOfCreditIssuanceController@updateExpense')->name('update.lc.issuance.expense');
                    Route::get('letter-of-credit-issuance/delete-expense/{expense}', 'LetterOfCreditIssuanceController@deleteExpense')->name('delete.lc.issuance.expense');
                    Route::get('letter-of-credit-issuance-remaining-balance', 'LetterOfCreditIssuanceController@getRemainingBalance')->name('get.remaining.balance.lc.issuance');
                    
                    // end letter of credit issuance
                    
                    Route::get('financial-institutions/{financialInstitution}/letter-of-credit-facility', 'LetterOfCreditFacilityController@index')->name('view.letter.of.credit.facility');
                    Route::get('financial-institutions/{financialInstitution}/letter-of-credit-facility/create', 'LetterOfCreditFacilityController@create')->name('create.letter.of.credit.facility');
                    Route::post('financial-institutions/{financialInstitution}/letter-of-credit-facility/create', 'LetterOfCreditFacilityController@store')->name('store.letter.of.credit.facility');
                    Route::get('financial-institutions/{financialInstitution}/letter-of-credit-facility/edit/{letterOfCreditFacility}', 'LetterOfCreditFacilityController@edit')->name('edit.letter.of.credit.facility');
                    Route::put('financial-institutions/{financialInstitution}/letter-of-credit-facility/update/{letterOfCreditFacility}', 'LetterOfCreditFacilityController@update')->name('update.letter.of.credit.facility');
                    Route::delete('financial-institutions/{financialInstitution}/letter-of-credit-facility/delete/{letterOfCreditFacility}', 'LetterOfCreditFacilityController@destroy')->name('delete.letter.of.credit.facility');

                    
                    
                    
                    
                    Route::get('aging-analysis/{modelType}', 'AgingController@index')->name('view.aging.analysis');
                    Route::post('aging-analysis/{modelType}', 'AgingController@result')->name('result.aging.analysis');
                    
                    
                    
                    
                    Route::get('effectiveness-index-report/collection', 'CollectionEffectivenessIndexController@index')->name('view.collections.effectiveness.index');
                    Route::post('effectiveness-index-report/collection', 'CollectionEffectivenessIndexController@result')->name('result.collections.effectiveness.index');


                    Route::get('safe-statement', 'SafeStatementController@index')->name('view.safe.statement');
                    Route::post('safe-statement', 'SafeStatementController@result')->name('result.safe.statement');

                    Route::get('cash-expense-statement', 'CashExpenseStatementController@index')->name('view.cash.expense.statement');
                    Route::post('cash-expense-statement', 'CashExpenseStatementController@result')->name('result.cash.expense.statement');
                    
                    Route::get('partners-statement', 'PartnersStatementController@index')->name('view.partners.statement');
                    Route::post('partners-statement', 'PartnersStatementController@result')->name('result.partners.statement');
                    
                    
                    Route::get('show-bank-statement', 'BankStatementController@index')->name('view.bank.statement');
                    Route::get('bank-statement', 'BankStatementController@result')->name('result.bank.statement');

                    Route::get('factoring-statement', 'FactoringStatementController@index')->name('view.factoring.statement');
                    Route::get('factoring-statement/result', 'FactoringStatementController@result')->name('result.factoring.statement');
                    Route::get('factoring-statement/currencies/{factoringCompany}', 'FactoringStatementController@getCurrencies');
                    Route::get('factoring-statement/contracts/{factoringCompany}/{currency}', 'FactoringStatementController@getContracts');

                    Route::get('factoring-charges-statement', 'FactoringChargesStatementController@index')->name('view.factoring.charges.statement');
                    Route::get('factoring-charges-statement/result', 'FactoringChargesStatementController@result')->name('result.factoring.charges.statement');
                    Route::get('factoring-charges-statement/currencies/{factoringCompany}', 'FactoringChargesStatementController@getCurrencies');
                    Route::get('factoring-charges-statement/contracts/{factoringCompany}/{currency}', 'FactoringChargesStatementController@getContracts');
                    
                    Route::post('update-commission-fees', 'BankStatementController@updateCommissionFees')->name('update.commission.fees');
                    Route::post('update-bank-statement-row-fees', 'BankStatementController@updateBankStatementRow')->name('update.bank.statement.debit.or.credit');
                    
                    Route::get('show-lg-by-beneficiary-name-report', 'LgByBeneficiaryNameReportController@index')->name('view.lg.by.beneficiary.name.report');
                    Route::get('lg-by-beneficiary-name-report', 'LgByBeneficiaryNameReportController@result')->name('result.lg.by.beneficiary.name.report');
                    
                    Route::get('show-lg-by-bank-name-report', 'LgByBankNameReportController@index')->name('view.lg.by.bank.name.report');
                    Route::get('lg-by-bank-name-report', 'LgByBankNameReportController@result')->name('result.lg.by.bank.name.report');
                    
                    Route::get('lg-lc-bank-statement', 'LGLCSBanktatementController@index')->name('view.lg.lc.bank.statement');
                    Route::post('lg-lc-bank-statement', 'LGLCSBanktatementController@result')->name('result.lg.lc.bank.statement');
                    Route::get('get-lg-lc-types', 'LGLCSBanktatementController@getLgOrLcType')->name('get.lc.or.lg.types');

                    Route::get('customer-balances/{modelType}', 'BalancesController@index')->name('view.balances');
                    Route::get('/cashvero-dashboard/cash', 'CustomerInvoiceDashboardController@viewCashDashboard')->name('view.customer.invoice.dashboard.cash');

                    Route::get('/cashvero-dashboard/forecast', 'CustomerInvoiceDashboardController@viewForecastDashboard')->name('view.customer.invoice.dashboard.forecast');
                    Route::get('/cashvero-dashboard/lglc', 'CustomerInvoiceDashboardController@viewLGLCDashboard')->name('view.lglc.dashboard');
                    // Route::get('/cashvero-dashboard-update-lg-dashboard','CustomerInvoiceDashboardController@updateLgDashboard')->name('update.lg.table.and.charts');
                    Route::get('/customer-balances/invoices-report/{partnerId}/{currency}/{modelType}', 'CustomerInvoiceDashboardController@showInvoiceReport')->name('view.invoice.report');
                    Route::get('/customer-balances/invoices-statement-report/{partnerId}/{currency}/{modelType}', 'CustomerInvoiceDashboardController@showInvoiceStatementReport')->name('view.invoice.statement.report');
                    Route::get('/customer-balances/total-net-balance-details/{currency}/{modelType}', 'BalancesController@showTotalNetBalanceDetailsReport')->name('show.total.net.balance.in');
                    // Route::get('collection-effectiveness-index-report',[]);
                    Route::get('get-contract-name-for-customer-or-supplier', 'getProjectsForCustomerOrSupplierController@handle')->name('get.projects.for.customer.or.supplier');
                    Route::get('get-po-or-so-for-contract', 'getPoOrSoFromContractController@handle')->name('get.po.or.so.from.contract');
                    Route::get('cashflow-report', 'CashFlowReportController@index')->name('view.cashflow.report');
                    Route::get('cashflow-report-result/{returnResultAsArray?}/{cashflowReport?}', 'CashFlowReportController@result')->name('result.cashflow.report');
					
					Route::prefix('reports/consolidated-cash-flow')
                ->name('reports.consolidated-cash-flow.')
                ->group(function () {
                    Route::get('/', [\App\Http\Controllers\Reports\ConsolidatedCashFlowReportController::class, 'index'])->name('index');
                    Route::get('/result', [\App\Http\Controllers\Reports\ConsolidatedCashFlowReportController::class, 'result'])->name('result');
                });
				
                    Route::delete('delete-cashflow-report/{cashflowReport}', 'CashFlowReportController@destroy')->name('delete.cashflow.report');
                    Route::get('contract-cashflow-report', 'ContractCashFlowReportController@index')->name('view.contract.cashflow.report');
					Route::get('contract-cashflow-report-result/{returnResultAsArray?}/{cashflowReport?}', 'ContractCashFlowReportController@result')->name('result.contract.cashflow.report');
                    

                    Route::get('withdrawals-settlements-report', 'WithdrawalsSettlementReportController@index')->name('view.withdrawals.settlement.report');
                    Route::post('withdrawals-settlements-report', 'WithdrawalsSettlementReportController@result')->name('result.withdrawals.settlement.report');

                    Route::get('refresh-withdrawal-dues-report', 'WithdrawalsSettlementReportController@refreshReport')->name('refresh.withdrawal.report'); // ajax

                    
                    Route::get('down-payment-contracts/{partnerId}/{modelType}/{currency}', 'DownPaymentContractsController@viewContractsWithDownPayments')->name('view.contracts.down.payments');
                    Route::get('down-payment-contracts-settlements/{downPaymentId}/{modelType}', 'DownPaymentContractsController@downPaymentSettlements')->name('view.down.payment.settlement');
                    Route::post('store-down-payment-settlement/{downPaymentId}/{partnerId}/{modelType}', 'DownPaymentContractsController@storeDownPaymentSettlement')->name('store.down.payment.settlement');
                    
                    Route::post('read-odoo-invoices', 'ReadOdooInvoices@handle')->name('read-odoo-invoices');
                    Route::post('read-odoo-contracts', 'ReadOdooContracts@handle')->name('read-odoo-contracts');
                    Route::post('read-odoo-partners', 'ReadOdooPartners@handle')->name('read-odoo-partners');
                    Route::post('send-odoo-collection-or-payments', 'SendOdooCollectionOrPayment@handle')->name('send-odoo-collection-or-payments');
                    Route::post('read-expenses', 'ReadOdooExpense@handle')->name('read-odoo-expenses');
                    Route::get('allocate-expense/{cashExpense}', 'CashExpenseController@viewAllocation')->name('cash.expense.allocate');
                    Route::put('allocate-expense/{cashExpense}', 'CashExpenseController@postAllocation')->name('allocate.odoo.cash.expense');
                    
                    Route::get('money-received', 'MoneyReceivedController@index')->name('view.money.receive');
                    Route::get('money-received/json', 'MoneyReceivedController@indexJson')->name('view.money.receive.json');
                    Route::post('resend-odoo-money/{moneyReceived}', 'MoneyReceivedController@resendToOdoo')->name('resend.with.odoo');
                    Route::get('money-received/create/{model?}', 'MoneyReceivedController@create')->name('create.money.receive');
                    Route::post('money-received/create', 'MoneyReceivedController@store')->name('store.money.receive');
                    Route::get('money-received/edit/{moneyReceived}', 'MoneyReceivedController@edit')->name('edit.money.receive');
                    Route::put('money-received/update/{moneyReceived}', 'MoneyReceivedController@update')->name('update.money.receive');
                    Route::delete('money-received/delete/{moneyReceived}', 'MoneyReceivedController@destroy')->name('delete.money.receive');
                    Route::get('money-received/get-invoice-numbers/{customer_name}/{currency?}', 'MoneyReceivedController@getInvoiceNumber'); // ajax request
                    Route::get('money-received/get-account-numbers-based-on-account-type/{accountType}/{currency}/{financialInstitutionId}', 'MoneyReceivedController@getAccountNumbersForAccountType'); // ajax request
                    Route::get('money-received/get-account-ids-based-on-account-type/{accountType}/{currency}/{financialInstitutionId}', 'MoneyReceivedController@getAccountIdsForAccountType'); // ajax request
                    Route::get('money-received/get-net-balance-based-on-account-number', 'MoneyReceivedController@updateNetBalanceBasedOnAccountNumber')->name('update.balance.and.net.balance.based.on.account.number');
                    Route::get('money-received/get-net-balance-based-on-account-id-by-ajax/{accountType}/{accountId}/{financialInstitutionId}', 'MoneyReceivedController@updateNetBalanceBasedOnAccountIdByAjax')->name('update.balance.and.net.balance.based.on.account.id.ajax');
                    Route::get('money-received/get-account-amount-based-on-account-id/{accountType}/{accountId}/{financialInstitutionId}', 'MoneyReceivedController@getAccountAmountForAccountId')->name('get.account.amount.based.on.account.id'); // ajax request
                    Route::get('get-customers-based-on-currency/{currencyName}', 'MoneyReceivedController@getCustomersBasedOnCurrency');
                    Route::get('get-partners-based-on-type/{currencyName}', 'MoneyReceivedController@getPartnersBasedOnCurrency');
                    Route::get('get-beneficiary-name-from-lg-issuance-based-on-currency', 'LetterOfGuaranteeIssuanceController@getBeneficiaryNameByCurrency')->name('get.beneficiary.name.by.currency');
                    Route::get('get-bank-name-from-lg-issuance-based-on-currency', 'LetterOfGuaranteeIssuanceController@getBankNameByCurrency')->name('get.bank.name.by.currency');
                    Route::post('confirmed-reviewed/{model}', 'MoneyReceivedController@markAsConfirmed')->name('confirmed.review');
                    
                    Route::get('money-received', 'MoneyReceivedController@index')->name('view.money.receive');
                    Route::get('money-received/json', 'MoneyReceivedController@indexJson')->name('view.money.receive.json');
                    Route::get('money-received/create/{model?}', 'MoneyReceivedController@create')->name('create.money.receive');
                    Route::post('money-received/create', 'MoneyReceivedController@store')->name('store.money.receive');
                    Route::get('money-received/edit/{moneyReceived}', 'MoneyReceivedController@edit')->name('edit.money.receive');
                    Route::put('money-received/update/{moneyReceived}', 'MoneyReceivedController@update')->name('update.money.receive');
                    Route::delete('money-received/delete/{moneyReceived}', 'MoneyReceivedController@destroy')->name('delete.money.receive');
                    Route::get('money-received/get-invoice-numbers/{customer_name}/{currency?}', 'MoneyReceivedController@getInvoiceNumber'); // ajax request
                    Route::get('money-received/get-account-numbers-based-on-account-type/{accountType}/{currency}/{financialInstitutionId}', 'MoneyReceivedController@getAccountNumbersForAccountType'); // ajax request
                    Route::get('get-interest-rate-for-financial-institution-id', 'FinancialInstitutionController@getInterestRateForFinancialInstitution')->name('get.interest.rate.for.financial.institution.id');

                    // money payments
                    
                    Route::get('money-payment', 'MoneyPaymentController@index')->name('view.money.payment');

                    Route::get('factoring/without-recourse', 'FactoringWithoutRecourseController@index')->name('factoring.without-recourse.index');
                    Route::get('factoring/without-recourse/create', 'FactoringWithoutRecourseController@create')->name('factoring.without-recourse.create');
                    Route::post('factoring/without-recourse/store', 'FactoringWithoutRecourseController@store')->name('factoring.without-recourse.store');
                    Route::get('factoring/without-recourse/{factoringTransaction}/edit', 'FactoringWithoutRecourseController@edit')->name('factoring.without-recourse.edit');
                    Route::put('factoring/without-recourse/{factoringTransaction}/update', 'FactoringWithoutRecourseController@update')->name('factoring.without-recourse.update');
                    Route::post('factoring/without-recourse/{factoringTransaction}/mark-as-settled', 'FactoringWithoutRecourseController@markAsSettled')->name('factoring.without-recourse.mark-as-settled');
                    Route::post('factoring/without-recourse/{factoringTransaction}/revert-settlement', 'FactoringWithoutRecourseController@revertSettlement')->name('factoring.without-recourse.revert-settlement');
                    Route::post('factoring/without-recourse/{factoringTransaction}/mark-difference-received', 'FactoringWithoutRecourseController@markDifferenceReceived')->name('factoring.without-recourse.mark-difference-received');
                    Route::post('factoring/without-recourse/{factoringTransaction}/revert-difference-received', 'FactoringWithoutRecourseController@revertDifferenceReceived')->name('factoring.without-recourse.revert-difference-received');
                    Route::delete('factoring/without-recourse/{factoringTransaction}', 'FactoringWithoutRecourseController@destroy')->name('factoring.without-recourse.destroy');
                    Route::get('factoring/without-recourse/contracts/{factoringCompany}', 'FactoringWithoutRecourseController@getContracts');
                    Route::get('factoring/without-recourse/currencies/{customerId}', 'FactoringWithoutRecourseController@getInvoiceCurrencies');
                    Route::get('factoring/without-recourse/invoices/{customerId}/{currency?}', 'FactoringWithoutRecourseController@getInvoices');
                    Route::post('factoring/without-recourse/calculate', 'FactoringWithoutRecourseController@calculate')->name('factoring.without-recourse.calculate');
                    Route::get('factoring/with-recourse', 'FactoringWithRecourseController@index')->name('factoring.with-recourse.index');
                    Route::get('factoring/with-recourse/create', 'FactoringWithRecourseController@create')->name('factoring.with-recourse.create');
                    Route::post('factoring/with-recourse/store', 'FactoringWithRecourseController@store')->name('factoring.with-recourse.store');
                    Route::get('factoring/with-recourse/{factoringTransaction}/edit', 'FactoringWithRecourseController@edit')->name('factoring.with-recourse.edit');
                    Route::put('factoring/with-recourse/{factoringTransaction}/update', 'FactoringWithRecourseController@update')->name('factoring.with-recourse.update');
                    Route::post('factoring/with-recourse/{factoringTransaction}/mark-collected', 'FactoringWithRecourseController@markCollected')->name('factoring.with-recourse.mark-collected');
                    Route::post('factoring/with-recourse/{factoringTransaction}/revert-collected', 'FactoringWithRecourseController@revertCollected')->name('factoring.with-recourse.revert-collected');
                    Route::post('factoring/with-recourse/{factoringTransaction}/mark-rejected', 'FactoringWithRecourseController@markRejected')->name('factoring.with-recourse.mark-rejected');
                    Route::post('factoring/with-recourse/{factoringTransaction}/revert-rejected', 'FactoringWithRecourseController@revertRejected')->name('factoring.with-recourse.revert-rejected');
                    Route::delete('factoring/with-recourse/{factoringTransaction}', 'FactoringWithRecourseController@destroy')->name('factoring.with-recourse.destroy');
                    Route::get('factoring/with-recourse/contracts/{factoringCompany}', 'FactoringWithRecourseController@getContracts');
                    Route::get('factoring/with-recourse/currencies/{customerId}', 'FactoringWithRecourseController@getInvoiceCurrencies');
                    Route::get('factoring/with-recourse/invoices/{customerId}/{currency?}', 'FactoringWithRecourseController@getInvoices');
                    Route::post('factoring/with-recourse/calculate', 'FactoringWithRecourseController@calculate')->name('factoring.with-recourse.calculate');
                    Route::get('money-payment/create/{model?}', 'MoneyPaymentController@create')->name('create.money.payment');
                    Route::post('money-payment/create', 'MoneyPaymentController@store')->name('store.money.payment');
                    Route::get('money-payment/edit/{moneyPayment}', 'MoneyPaymentController@edit')->name('edit.money.payment');
                    Route::put('money-payment/update/{moneyPayment}', 'MoneyPaymentController@update')->name('update.money.payment');
                    Route::delete('money-payment/delete/{moneyPayment}', 'MoneyPaymentController@destroy')->name('delete.money.payment');
                    Route::get('money-payment/get-invoice-numbers/{supplier_name}/{currency?}', 'MoneyPaymentController@getInvoiceNumber'); // ajax request
                    Route::get('money-payment/get-account-numbers-based-on-account-type/{accountType}/{currency}/{financialInstitutionId}', 'MoneyPaymentController@getAccountNumbersForAccountType'); // ajax request
                    Route::post('mark-payable-cheques-as-paid', 'MoneyPaymentController@markChequesAsPaid')->name('payable.cheque.mark.as.paid');
                    Route::post('mark-outgoing-transfer-as-paid', 'MoneyPaymentController@markOutgoingTransfersAsPaid')->name('outgoing.transfer.mark.as.paid');
                    Route::get('get-supplier-invoices', 'SupplierInvoicesController@getSupplierInvoicesForSupplier')->name('get.supplier.invoices');
                    Route::get('get-suppliers-based-on-currency/{currencyName}', 'MoneyPaymentController@getSuppliersBasedOnCurrency');
                    Route::get('get-current-end-balance-of-current-account', 'MoneyPaymentController@getCashInSafeStatementEndBalance')->name('get.current.end.balance.of.cash.in.safe.statement');
                    // cash expense
                    Route::get('get-exchange-rate-for-date-and-currencies', 'ForeignExchangeRateController@getExchangeRate');
                    
                    Route::get('odoo-approved-expenses', 'OdooExpensesController@index')->name('odoo-expenses.index');
                    // Route::get('odoo-approved-expenses/create','OdooExpensesController@create')->name('odoo-expenses.create');
                    Route::post('odoo-approved-expenses/mark-as-paid', 'OdooExpensesController@markAsPaid')->name('odoo-expenses.mark.as.paid');
                    // Route::get('odoo-approved-expenses/{odooExpense}/edit','OdooExpensesController@edit')->name('odoo-expenses.edit');
                    // Route::put('odoo-approved-expenses/{odooExpense}/update','OdooExpensesController@update')->name('odoo-expenses.update');
                    Route::delete('odoo-approved-expenses/{odooExpense}/delete', 'OdooExpensesController@destroy')->name('odoo-expenses.destroy');
                    
                    Route::get('cash-expense', 'CashExpenseController@index')->name('view.cash.expense');
                    Route::get('cash-expense/create/{model?}', 'CashExpenseController@create')->name('create.cash.expense');
                    Route::post('cash-expense/create', 'CashExpenseController@store')->name('store.cash.expense');
                    Route::get('cash-expense/edit/{cashExpense}', 'CashExpenseController@edit')->name('edit.cash.expense');
                   
                    Route::put('cash-expense/update/{cashExpense}', 'CashExpenseController@update')->name('update.cash.expense');
                    Route::delete('cash-expense/delete/{cashExpense}', 'CashExpenseController@destroy')->name('delete.cash.expense');
                    Route::get('cash-expense/get-account-numbers-based-on-account-type/{accountType}/{currency}/{financialInstitutionId}', 'CashExpenseController@getAccountNumbersForAccountType'); // ajax request
                    Route::post('cash-expense-mark-payable-cheques-as-paid', 'CashExpenseController@markChequesAsPaid')->name('cash.expense.payable.cheque.mark.as.paid');
                    Route::post('cash-expense-mark-outgoing-transfer-as-paid', 'CashExpenseController@markOutgoingTransfersAsPaid')->name('cash.expense.outgoing.transfer.mark.as.paid');

                    
                    Route::post('adjust-customer-due-invoices', 'CashFlowReportController@adjustCustomerDueInvoices')->name('adjust.customer.dues.invoices');
                    Route::post('adjust-loan-past-due-installments', 'CashFlowReportController@adjustLoanPastDueInstallments')->name('adjust.loan.past.dues.installments');
                    Route::post('save-projections', 'CashFlowReportController@saveProjection')->name('save.projection');
                    // Route::post('adjust-loan-past-due-installments','CashFlowReportController@storeProjection')->name('store.projection');
                    
                    // Route::get('unapplied-amounts/{partnerId}/{modelType}', 'UnappliedAmountController@index')->name('view.settlement.by.unapplied.amounts');
                    // Route::get('unapplied-amounts/create/{customerInvoiceId}/{modelType}', 'UnappliedAmountController@create')->name('create.settlement.by.unapplied.amounts');
                    // Route::post('unapplied-amounts/create/{modelType}', 'UnappliedAmountController@store')->name('store.settlement.by.unapplied.amounts');
                    // Route::put('unapplied-amounts/update/{modelType}/{unappliedAmountId}/{settlementId}', 'UnappliedAmountController@update')->name('update.settlement.by.unapplied.amounts');
                    // Route::get('unapplied-amounts/edit/{invoice_number}/{settlementId}/{modelType}', 'UnappliedAmountController@edit')->name('edit.settlement.by.unapplied.amounts');
                });

             
      

                Route::post('send-cheques-to-collection', 'MoneyReceivedController@sendToCollection')->name('cheque.send.to.collection');
                Route::get('send-cheques-to-safe/{moneyReceived}', 'MoneyReceivedController@sendToSafe')->name('cheque.send.to.safe');
                Route::post('send-cheques-to-collection/{moneyReceived}', 'MoneyReceivedController@applyCollection')->name('cheque.apply.collection');
                Route::get('send-cheques-to-rejected-safe/{moneyReceived}', 'MoneyReceivedController@sendToSafeAsRejected')->name('cheque.send.to.rejected.safe');
                Route::get('send-cheques-to-under-collection-safe/{moneyReceived}', 'MoneyReceivedController@sendToUnderCollection')->name('cheque.send.to.under.collection');
                Route::get('down-payments/get-contracts-for-customer-with-start-and-end-date', 'MoneyReceivedController@getContractsForCustomerWithStartAndEndDate')->name('get.contracts.for.customer.with.start.and.end.date'); // ajax request
                Route::get('down-payments/get-contracts-for-customer', 'MoneyReceivedController@getContractsForCustomer')->name('get.contracts.for.customer'); // ajax request
                Route::get('down-payments/get-contracts-for-supplier', 'MoneyPaymentController@getContractsForSupplier')->name('get.contracts.for.supplier'); // ajax request
                Route::get('down-payments/get-sales-orders-for-contract/{contract_id}/{currency?}', 'MoneyReceivedController@getSalesOrdersForContract'); // ajax request
                Route::get('down-payments/get-purchases-orders-for-contract/{contract_id}/{currency?}', 'MoneyPaymentController@getSalesOrdersForContract'); // ajax request
                Route::post('update-payable-cheques/{moneyPayment}/{payableCheque}', 'MoneyPaymentController@updateOpeningPayableCheque')->name('update.opening.payable.cheque');
          		  Route::post('store-new', 'DynamicItemsController@storeNewModal')->name('admin.store.new.modal.dynamic');

                Route::post('/store-dynamic-items', 'DynamicItemsController@storeSubItems')->name('store.dynamic.items.names');
                Route::get('/create-item/{model}', 'SalesGatheringTestController@createModel')->name('create.sales.form');
                Route::post('/create-item/{model}', 'SalesGatheringTestController@storeModel')->name('admin.store.analysis');
                Route::post('/close-period-action', 'ClosePeriodController@execute')->name('store.close.period');

                Route::get('/create-item/{model}/edit/{modelId}', 'SalesGatheringTestController@editModel')->name('edit.sales.form');
                Route::post('/create-item/{model}/update/{modelId}', 'SalesGatheringTestController@updateModel')->name('admin.update.analysis');


               


                //########### Exportable Fields Selection Routes ############
                Route::get('fieldsToBeExported/{model}/{view}', 'ExportTable@customizedTableField')->name('table.fields.selection.view');
                Route::post('fieldsToBeExportedSave/{model}/{modelName}', 'ExportTable@customizedTableFieldSave')->name('table.fields.selection.save');
            });
        }
    );
});

Route::delete('deleteMultiRowsFromCaching/{company}/{modelName}', [DeleteMultiRowsFromCaching::class, '__invoke'])->name('deleteMultiRowsFromCaching');
Route::get('deleteAllRowsFromCaching/{company}/{modelType}', [DeleteAllRowsFromCaching::class, '__invoke'])->name('deleteAllCaches');
Route::post('get-uploading-percentage/{companyId}/{modelName}', [getUploadPercentage::class, '__invoke']);
Route::get('{lang}/remove-company-image/{company}', function ($lang, Company $company) {
    if ($company->getFirstMedia('default')) {
        $company->getFirstMedia('default')->delete();
    }
    
    return redirect()->back()->with('success', __('Company Image Has Been Deleted Successfully'));
})->name('remove.company.image');

Route::get('getStartDateAndEndDateOfIncomeStatementForCompany', 'HomeController@getIncomeStatementStartDateAndEndDate');
Route::get('removeSessionForRedirect', function () {
    if (session()->has('redirectTo')) {
        $url = session()->get('redirectTo');
        session()->forget('redirectTo');

        return response()->json([
            'status' => true,
            'url' => $url
        ]);
    }
});



// Route::get('salah', );

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'auth', 'checkIfAccountExpired', 'can:view cash flow report'],
    ],
    function () {
        Route::prefix('{company}')->group(function () {
            
        });
    }
);
