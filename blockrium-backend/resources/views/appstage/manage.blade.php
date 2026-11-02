@include('admin.includes.header')

@include('admin.includes.sidebar') 



  <!-- Content Wrapper. Contains page content -->

  <div class="content-wrapper">

    <!-- Content Header (Page header) -->

    <section class="content-header">

      <div class="container-fluid">

  @include('flash/flash-message')

        <!-- /.row -->

        <div class="row">

          <div class="col-12">

            <div class="card card-primary">

              <div class="card-header">

                <h3 class="card-title">Manage App Stage </h3>

                </div>

              </div>

              <!-- /.card-header -->

              <div class="card-body table-responsive p-0">

                <table class="table table-hover text-nowrap">

                  <thead>

                    <tr>

                      <th>Id</th>
                      <th>Paragraph</th>
                      <th>Image</th>
                      
                      <th>Action</th>

                    </tr>

                  </thead>

                  <tbody>

                  @foreach($appstage as $key => $stage)

                    <tr>

                      <td>{{$stage->id}}</td>  
                      <td>{{$stage->title}}</td>
                      <td>{{$stage->stage}}</td>
                      <td>{{$stage->miningrate}}</td>

                    <td>

                      <a href="{{url('admin/edit-app-stage')}}/{{ $stage->id }}" class="btn btn-success btn-xs" ><i class="fa fa-edit" aria-hidden="true"></i></a>
				
                      <a class="btn btn-danger"  href="{{url('admin/delete_app-stage')}}/{{$stage->id}}" data-id="{{$stage->id}}"title="Delete"><i class="fa fa-trash" aria-hidden="true"></i></a>
                      </td>

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

    $(".deletepage").click(function(e){

		 var id = $(this).attr('data-id');

		if(confirm("Are you sure you want to delete this?")){

        e.preventDefault();

		$.ajax({

           type:'GET',

           url:"/admin/delete_banner",

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