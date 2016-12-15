<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

// Authentication routes...
Route::get('auth/login', 'Auth\AuthController@getLogin');
Route::post('auth/login', 'Auth\AuthController@postLogin');
Route::get('auth/logout', 'Auth\AuthController@getLogout');

Route::get('/home', function(){ return "Access denied"; });

//Route::get('/',function(){ return view('db/show'); });

//Route::get('/{time?}',"DashboardController@show");
Route::group(['middleware' => 'auth'], function()
{	
	Route::group(['middleware' => ['role:admin']], function() {
		// Registration routes...
		Route::match(array('GET', 'POST'), 'admin/create_user', [ 'uses' => 'AdminController@create_user']);
		Route::get('admin/list_users', 'AdminController@list_users');	   
	});

	Route::controllers([
	    'results'       => 'ResultsController',
	]);

	Route::match(array('GET', 'POST'),'/change_password',['uses'=>'AdminController@change_password']);


	Route::get('/facilities', ['middleware' => ['permission:print_results'], 'as' => 'facilities', 'uses' => 'ResultsController@facilities']);

	Route::match(array('GET', 'POST'), '/result/{id?}/', [ 'middleware' => ['permission:qc'], 'as' => 'result', 'uses' => 'ResultsController@getResult']);
	Route::get('/qc', ['middleware' => ['permission:qc'], 'as' => 'qc', 'uses' => 'QCController@index']);
	Route::match(array('GET', 'POST'), '/qc/{id}', ['middleware' => ['permission:qc'], 'as' => 'qc', 'uses' => 'QCController@qc']);
	Route::get('/qc/wk_search/{q}/', [ 'middleware' => ['permission:qc'], 'as' => 'qc_worksheet_search', 'uses' => 'QCController@worksheet_search']);
	Route::get('/log_printing/',['middleware' => ['permission:print_results'], 'as' => 'log_printing', 'uses'=>'ResultsController@log_printing']);
});
Route::get("/","DashboardController@init");

Route::get("/live","DashboardController@live");

Route::get("/other_data/","DashboardController@other_data");

//Route::post('/downloadCsv', 'DashboardController@downloadCsv');

/*Route::get('/w',function(){
	return view("welcome");
});*/