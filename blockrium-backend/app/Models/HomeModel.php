<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Session;
class HomeModel extends Model
{
	function getAllData($table,$limit){
    	$results = DB::table($table)->paginate($limit);
		return $results;
	}
	
	function product_data($cat,$sub_category){
    	$results = DB::table('tbl_product as p')->leftJoin('tbl_category as c', 'c.cat_id', '=', 'p.category')->leftJoin('tbl_category as s', 's.cat_id', '=', 'p.sub_category')->where('p.category','=',$cat)->where('p.sub_category','=',$sub_category)->paginate(20);
		//->toSql();->appends(['sort' => 'votes']);
		
		return $results;
	}
	function product_databycat($cat){
    	$results = DB::table('tbl_product as p')->leftJoin('tbl_category as c', 'c.cat_id', '=', 'p.category')->leftJoin('tbl_category as s', 's.cat_id', '=', 'p.sub_category')->where('p.category','=',$cat)->paginate(20);
		//->toSql();->appends(['sort' => 'votes']);
		
		return $results;
	}
	function getcategory($cat){
    	$results = DB::table('tbl_category')->where('cat_name',$cat)->first();
		return $results;
	}
	function getsubcategory($cat_id,$subcat){
    	$results = DB::table('tbl_category')->where('cat_name',$subcat)->where('parent_id',$cat_id)->first();
		return $results;
	}
	function getproduct($product){
    	$results = DB::table('tbl_product')->where('name',$product)->first();
		return $results;
	}
	function getsearchproduct($search,$type){
		if($type=='title'){
			$results = DB::table('tbl_product as p')->select('p.*','c.cat_name as cname','s.cat_name as scname')->leftJoin('tbl_category as c', 'c.cat_id', '=', 'p.category')->leftJoin('tbl_category as s', 's.cat_id', '=', 'p.sub_category')->where('p.name', 'LIKE', '%' . $search . '%')->paginate(20);
		}elseif($type=='sku'){
			$results = DB::table('tbl_product_to_manufacturer as ptm')->select('ptm.*','p.*','c.cat_name as cname','s.cat_name as scname')->leftjoin('tbl_product as p','p.sku', '=', 'ptm.product_code')->leftJoin('tbl_category as c', 'c.cat_id', '=', 'p.category')->leftJoin('tbl_category as s', 's.cat_id', '=', 'p.sub_category')->where('ptm.product_code', 'LIKE', '%' . $search . '%')->paginate(20);
		}elseif($type=='home_search'){
			$results = DB::table('tbl_product as p')->select('p.*','c.cat_name as cname','s.cat_name as scname')->leftJoin('tbl_category as c', 'c.cat_id', '=', 'p.category')->leftJoin('tbl_category as s', 's.cat_id', '=', 'p.sub_category')->where('p.name', 'LIKE', '%' . $search . '%')->paginate(20);
			
		}
		return $results;
	}
	function userlogin($username,$password){
		$results= DB::table('tblagents')
			->where('email','=',$username)
			//->where('status','=','Y')
			->first();

		if (!$results) {
			return null;
		}

		if (Hash::check($password, $results->password)) {
			return $results;
		}

		if (hash_equals($results->password, md5($password))) {
			DB::table('tblagents')
				->where('email', $username)
				->update(['password' => Hash::make($password)]);

			return DB::table('tblagents')
				->where('email', $username)
				->first();
		}

		return null;	
	}
	function cartdetail($customer_id){
		$results = DB::table('tbl_cart')->leftJoin('tbl_product','tbl_cart.product_id','=','tbl_product.Product_id')->where('customer_id',$customer_id)->get();
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
	
	
	
	
	
	
	  
	
    /* Admin Login */

	/* Check user exist or not */
	function checkuser($username){
		$results= DB::table('tbl_member')
			->where('email','=',$username)
			->first();
		return $results;	
	}
	function checkdetailsuser($id){
		$results= DB::table('tbl_member')
			->where('int_memberid','=',$id)
			->first();
		return $results;	
	}
	function user_update($data,$id){
		$results = DB::table('tbl_member')
            ->where('int_memberid', $id)
            ->update($data);
			return $results;	
	}

	public function businessdetails($id){
		
		$results=DB::table('tblourbusiness')
		
		->where('slug',$id)->first();
			return $results;	
	}

	public function ProjectInformation($search,$typeid,$country,$state){
		
			$results=DB::table('tblprojects')
			->JOIN('tblprojecttypes as projecttype', 'tblprojects.typeid','=','projecttype.typeid')
			->leftjoin('tbl_states as state', 'tblprojects.stateid','=','state.stateid')
			->leftjoin('tblcountry as country', 'tblprojects.countryid','=','country.countryid')
			->select('tblprojects.*','projecttype.*','country.countryname as cname','state.statename as sname')
			->where('projectstatus', 'A')->where('tblprojects.countryid', 'Like', '%' .$country. '%')->orWhere('tblprojects.stateid', 'Like', '%' .$state. '%')->paginate(10)->withQueryString();;
		
			//dd($results);
		return $results;
	}
	
	public function moredetails($id){
		
		$results=DB::table('tblprojects')
		->leftjoin('tblprojecttypes as projecttype', 'tblprojects.typeid','=','projecttype.typeid')
		->leftjoin('tblprojectsdocs as projectdoc', 'tblprojects.projectid','=','projectdoc.projectid')
		->leftjoin('tblprojectsimages as projectimg', 'tblprojects.projectid','=','projectimg.projectid')
		->leftjoin('tblprojectsvideos as projectvdo', 'tblprojects.projectid','=','projectvdo.projectid')
		->select('tblprojects.*','projecttype.type as typename','projectdoc.*','projectimg.*','projectvdo.*')
		->where('slug',$id)->get();
			return $results;	
	}

	function moredetails_submit($id){
		$results= DB::table('tblprojects')
		->leftjoin('tblprojecttypes as projecttype', 'tblprojects.typeid','=','projecttype.typeid')
		->leftjoin('tbl_states as state', 'tblprojects.stateid','=','state.stateid')
		->leftjoin('tblcountry as country', 'tblprojects.countryid','=','country.countryid')
		->leftjoin('tblprojectsdocs as projectdoc', 'tblprojects.projectid','=','projectdoc.projectid')
		->leftjoin('tblprojectsimages as projectimg', 'tblprojects.projectid','=','projectimg.projectid')
		->leftjoin('tblprojectsvideos as projectvdo', 'tblprojects.projectid','=','projectvdo.projectid')
		->select('tblprojects.*','projecttype.type as typename','country.countryname as cname','state.statename as sname','projectdoc.*','projectimg.*','projectvdo.*')
		
		->where('slug',$id)->first();
		   
		return $results;	
	}

	function manage_documents($id){
		
		$results = DB::table('tblprojectsdocs')->where('projectid',$id)->get();
	
		return $results; 
		 
	}

	function manage_photo($id){
		
		$results = DB::table('tblprojectsimages')
		->JOIN('tblprojects', 'tblprojects.projectid','=','tblprojects.projectid')
		->select('tblprojects.*','tblprojectsimages.*')
		->where('tblprojectsimages.projectid',$id)->get();
		
		return $results; 
		 
	}

	function manage_photo_all($id){
		
		$results = DB::table('tblprojectsimages')
		->where('projectid',$id)->get();
		
		return $results; 
		 
	}

	function projectdetails($id){
		
		$results = DB::table('tblprojects')
		->JOIN('tblprojecttypes as projecttype', 'tblprojects.typeid','=','projecttype.typeid')
		->leftjoin('tbl_states as state', 'tblprojects.stateid','=','state.stateid')
		->leftjoin('tblcountry as country', 'tblprojects.countryid','=','country.countryid')
		->select('tblprojects.*','projecttype.*','country.countryname as cname','state.statename as sname')
		->where('slug',$id)->first();
		
		return $results; 
		 
	}

 public $table="tblourbusiness";
  protected $fillable=['businessid ','businesstitle','shortdescription','detaileddescription','businesslogo','businessimage','businessstatus'];
	
}
