<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;   
use Illuminate\Support\Facades\DB;



class ResUserModel extends Model
{
    public $timestamps = false;
	
	function getAllData($table){
    	$results = DB::table($table)->get();
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
	
	function getResultByLike($date){
        $result = DB::table('ad_timeline')->where('created_date_time','like',"%$date%")->get();
        return $result;
	}
	function getLastRowById($table,$where,$id,$orderby){
        $last_row=DB::table($table)->where($where)->orderBy($id,$orderby)->first();
        return $last_row;
	}

	function tablecount($table){
		$results = DB::table($table)->count();
		return $results;	
	}

	function User_status_update($id,$data){
	
		$results = DB::table('res_users')->where('id', $id)->update($data);
			return $results;

	}
	
	

}