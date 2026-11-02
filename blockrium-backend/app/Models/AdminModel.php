<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;







class AdminModel extends Model

{

	function getAllData($table,$limit){

    	$results = DB::table($table)->paginate($limit);

		return $results;

	}

		

	function getAllDataOrderBy($table,$id,$orderby){

    	$results = DB::table($table)->orderBy($id,$orderby)->get();

		return $results;

	}

	function getAllDataSelect($table,$select){

    	$results = DB::table($table)->select($select)->get();

		return $results;

	}

	function getRowById($table,$where){

	$row = DB::table($table)->where($where)->first();

	return $row;

	}

	function insert($table, $data){

		$result = DB::table($table)->insert($data);

		//return $result;

		return DB::getPdo()->lastInsertId();

	}

	function getResultById($table, $where){

	$result = DB::table($table)->where($where)->get();

	return $result;

	}

	function update_row($table, $data, $where){

		$result = DB::table($table)->where($where)->update($data);

		//return $result;	

		if($result){

			return true;

		}else{

			return false;

		} 

	} 

	

	function getLastRowById($table,$where,$id,$orderby){

	$last_row=DB::table($table)->where($where)->orderBy($id,$orderby)->first();

	return $last_row;

	}

	

    /* Admin Login */

	function userlogin($username,$password){

		$results= DB::table('tbl_admin')

			->where('username','=',$username)

			->first();

		if (!$results) {
			return null;
		}

		if (Hash::check($password, $results->password)) {
			return $results;
		}

		if (hash_equals($results->password, md5($password))) {
			DB::table('tbl_admin')
				->where('admin_id', $results->admin_id)
				->update(['password' => Hash::make($password)]);

			return DB::table('tbl_admin')
				->where('admin_id', $results->admin_id)
				->first();
		}

		return null;	

	}

	/* Check user exist or not */

	function checkuser($username){

		$results= DB::table('tbl_admin')

			->where('username','=',$username)

			->first();

		return $results;	

	}

	function checkdetailsuser($id){

		$results= DB::table('tbl_admin')

			->where('admin_id','=',$id)

			->first();

		return $results;	

	}	

	function checkcurrentpassword($id,$new_password){

		$results= DB::table('tbl_admin')

			->where('admin_id','=',$id)

			->first();

		if (!$results) {
			return null;
		}

		if (Hash::check($new_password, $results->password)) {
			return $results;
		}

		if (hash_equals($results->password, md5($new_password))) {
			DB::table('tbl_admin')
				->where('admin_id', $id)
				->update(['password' => Hash::make($new_password)]);

			return DB::table('tbl_admin')->where('admin_id', $id)->first();
		}

		return null;	

	}

	function user_update($data,$id){

		$results = DB::table('tbl_admin')

            ->where('admin_id', $id)

            ->update($data);

			return $results;	

	}

	function user_status_update($id,$data){
		$results = DB::table('res_users')->where('id', $id)->update($data);
		return $results;
	}
	
	function user_is_active_update($id,$data){
		$results = DB::table('res_users')->where('id', $id)->update($data);
		return $results;
	}

	function deleteUsers($id){
		$result = DB::table('res_users')->where('id', $id)->delete();
		return $result;	
	}

	function deleteBanner($id){
		$result = DB::table('banners')->where('id', $id)->delete();
		return $result;	
	}
	
	function deletenews($id){
		$result = DB::table('custom_news')->where('id', $id)->delete();
		return $result;	
	}
	
	function deleteapk($id){
		$result = DB::table('app_apk')->where('id', $id)->delete();
		return $result;	
	}
	
	function deleteGameCategory($id){
		$result = DB::table('game_category')->where('id', $id)->delete();
		return $result;	
	}
	
	function GameCategoryName(){
    	$result = DB::table('game_category')
    	->get();
    	
    	return $result;		
    }
    
    function deleteGame($id){
		$result = DB::table('games')->where('id', $id)->delete();
		return $result;	
	}
	
	
	function userlisting($search){
		if(empty($search)){
			$results= DB::table('res_users')->paginate(10)->withQueryString();
		}else{
			$results= DB::table('res_users')->where('name', 'Like', '%' .$search. '%')->orwhere('email', 'Like', '%' .$search. '%')->paginate(10)->withQueryString();
		}
		return $results;
	}
	
	function deleteTasks($id){
		$result = DB::table('tasks')->where('id', $id)->delete();
		return $result;	
	}
	
	

}
