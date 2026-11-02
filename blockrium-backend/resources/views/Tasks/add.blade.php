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

				        <h3 class="card-title">Add Tasks</h3>			

              </div>

			  

              <!-- /.card-header -->

              <!-- form start -->

              <form method="POST" action="{{url('admin/add/tasks')}}" enctype="multipart/form-data">

			          @csrf
                <div class="form-group">
                    <label for="exampleInputName">Task Name Title</label>
                    <input type="text" name="title" id="title"  class="form-control" required/> 
                  </div> 
                  <div class="form-group">
                    <label for="exampleInputName">Task Link</label>
                    <input type="text" name="link" id="link"  class="form-control" required/> 
                  </div> 
                  <div class="form-group">
                    <label for="exampleInputName">Coin Reward</label>
                    <input type="number" name="coins" id="coins"  class="form-control" required/> 
                  </div>
                  <div class="form-group">
                    <label for="exampleInputName">Is Featured</label>
                    <input type="number" name="is_featured" id="is_featured"  class="form-control" placeholder="0 or 1"/> 
                  </div>
                  <div class="form-group">
                    <label for="exampleInputName">Task Icon</label>
                    <input type="file" accept=".jpg, .png" name="image" id="photo"  class="form-control"/> 
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