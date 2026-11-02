@include('admin.includes.header')

@include('admin.includes.sidebar') 

    

	 <div class="content-wrapper">

    <!-- Content Header (Page header) -->

    <section class="content-header">

      <div class="container-fluid">

        <div class="row ">

		      <div class="col-md-12">

            <div class="card card-primary">

              <div class="card-header">

				        
				        @if(empty($response))
                            <h3 class="card-title">Add App Stage</h3>
                    	@else
                    	<h3 class="card-title">Add App Stage</h3>
                    	@endif

              </div>

			  

              <!-- /.card-header -->

              <!-- form start -->

              <form method="POST" action="{{url('admin/add/app-stage')}}" enctype="multipart/form-data">
                @if(!empty($response))
				 <input type="hidden" name="id" value="{{$response->id}}">
				@endif
			          @csrf
                <div class="form-group">
                    <label for="exampleInputName">Title</label>
                    <input type="text" name="title" id="title" value="@if(!empty($response)){{$response->title}}@endif"  class="form-control"/> 
                  </div> 
                  <div class="form-group">
                    <label for="exampleInputName">App Stage</label>
                    <input type="text" name="stage" id="stage" value="@if(!empty($response)){{$response->stage}}@endif"  class="form-control"/> 
                  </div>
                  <div class="form-group">
                    <label for="exampleInputName">Mining Rate P/H</label>
                    <input type="text" name="miningrate" id="miningrate" value="@if(!empty($response)){{$response->miningrate}}@endif"  class="form-control"/> 
                  </div> 
                                  

                <div class="card-footer">

				 

				          <button type="submit" class="btn btn-primary">Submit</button>

				

                </div>

              </form>

            </div>

          </div>

         

        </div>

        <!-- /.row -->

      </div><!-- /.container-fluid -->

    </section>

    <!-- /.content -->

  </div>

  

@include('admin.includes.footer')



 <script>

            $(document).ready(() => {

                $("#photo").change(function () {

                    const file = this.files[0];

                    if (file) {

                        let reader = new FileReader();

                        reader.onload = function (event) {

                            $("#imgPreview")

                              .attr("src", event.target.result);

                        };

                        reader.readAsDataURL(file);

                    }

                });

            });

        </script>