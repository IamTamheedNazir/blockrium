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
                            <h3 class="card-title">Add Game</h3>
                    	@else
                    	<h3 class="card-title">Edit Game</h3>
                    	@endif
				        			

              </div>

			  

              <!-- /.card-header -->

              <!-- form start -->

              <form method="POST" action="{{url('admin/add/game')}}" enctype="multipart/form-data">
@if(!empty($response))
				 <input type="hidden" name="id" value="{{$response->id}}">
				@endif
			          @csrf
                <div class="form-group">
                    <label for="exampleInputName">Game Name</label>
                    <input type="text" name="title" id="title" value="@if(!empty($response)){{$response->title}}@endif"  class="form-control"/> 
                  </div> 
                <div class="form-group">
                    <label for="exampleInputName">Select Game Category</label>
                        <select name='game_category_id' class="form-control">
                        
                        @foreach($gamecategory as $res)
                        
                            <option @if($response !='') @if($res->title == $response->game_category_id){{ 'selected' }}@endif @endif value="{{$res->id}}">{{$res->title}}</option>
                           

                            @endforeach							
                        </select>
                    </div>
                  <div class="form-group">
                    <label for="exampleInputName">Upload Logo</label>
                    <input type="file" name="logo" id="logo"  class="form-control"/> 
                  </div>
                  
                  <div class="form-group">
                    <label for="exampleInputName">Game URL</label>
                    <input type="text" name="url" id="url" value="@if(!empty($response)){{$response->url}}@endif"  class="form-control"/> 
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