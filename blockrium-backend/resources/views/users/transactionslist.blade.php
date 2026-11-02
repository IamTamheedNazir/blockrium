@include('admin.includes.header')

@include('admin.includes.sidebar') 

<style>

  td.teamFlag img{

    

    height: 103px;

    width: 136px;



  }

.spc_item {

    display: flex;

}

.input-group.input-group-sm {

    width: fit-content;

    float: right;

}

.Reset-btn {

    background: transparent;

    border: none;

    padding: 0px 5px !important;

}

.spc_item input {

    margin: 0px 5px;

    padding: 10px;

}

.input-group-append button {

    padding: 0px 10px;

}

</style>

  <!-- Content Wrapper. Contains page content -->

  <div class="content-wrapper">

    <!-- Content Header (Page header) -->

    <section class="content-header">

      <div class="container-fluid">



        <!-- /.row -->

        <div class="row">

          <div class="col-12">

            <div class="card card-primary">

              <div class="card-header">

                <h3 class="card-title">Manage Transaction</h3>

                  <form action="manage_agent" class="manage_cus" method="GET" >

                    <div class="card-tools">

                      <div class="input-group input-group-sm" >

                        <div class="spc_item">

                          <input type="text" name="search" class="form-control" value="@if(!empty($search)){{$search}}@endif" placeholder="Search">



                          <div class="input-group-append">

                            <button type="submit" class="btn btn-default">

                              <i class="fas fa-search"></i>

                            </button>

                            <button class="Reset-btn"><a href="{{url('/admin/')}}" class="btn  btn-info " >Reset</a> </button>

                          </div>

                        </div>

                      </div>

                    </div>

				          </form>

              </div>

              <!-- /.card-header -->

			        @include('flash/flash-message')

              <div class="card-body table-responsive p-0">

                <table class="table table-hover text-nowrap">

                  <thead>

                    <tr>

                        <th>ID</th>
                        <th>Name</th>
                        <th>Amount</th>
                        <th>Date</th>

                    </tr> 

                  </thead>

                  <tbody>

				    @foreach($transactions_data as $key => $alldata)
              <tr>
                  <td>{{$alldata->id}}</td>
                  <td>{{$alldata->name}}</td>
                  <td>{{$alldata->totaltoken}}</td>
                  <td>{{$alldata->created_at}}</td>
                 
                   

              </tr>

              


                    @endforeach

                  </tbody>

                </table>

			

              </div>

			         

              <!-- /.card-body -->

            </div>

            <!-- /.card -->

          </div>

        </div>

        <!-- /.row -->

        <!-- /.row -->

      </div><!-- /.container-fluid -->

    </section>

    <!-- /.content -->

  </div>

  <!-- /.content-wrapper -->

  @include('admin.includes.footer')

  

  

  

  <script type="text/javascript">

  

    $(".changestatus").click(function(e){

		 var pageid = $(this).attr('data-id');

        var pagestatus = $(this).attr('data-status');

		if(status=='A'){

			var newstatus='Deactivate';

		}else{

			var newstatus='Activate';

			

		}

		

		if(confirm("Are you sure you want to "+newstatus+" this?")){

        e.preventDefault();

		$.ajax({

           type:'GET',

           url:"/FalcoGroup/admin/manage_agent/status",

           data:{agentid:pageid, agentstatus:pagestatus},

           success:function(data){

              location.reload();

           }

        });

		}else{

        return false;

    }

  

    });

	

	

	

    $(".deletepage").click(function(e){

		 var id = $(this).attr('data-id');

		// console.log(id);

		if(confirm("Are you sure you want to delete this?")){

        e.preventDefault();

		$.ajax({

           type:'GET', 

           url:"/admin/manage_agent",

           data:{id:id},

           success:function(data){

              location.reload();

           }

        });

		}else{

        return false;

    }

  

    });

</script>

   