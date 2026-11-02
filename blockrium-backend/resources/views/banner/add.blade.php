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

				        <h3 class="card-title">Add Banner</h3>			

              </div>

			  

              <!-- /.card-header -->

              <!-- form start -->

              <form method="POST" action="{{url('admin/add/banner')}}" enctype="multipart/form-data">

			          @csrf
                <div class="form-group">
                    <label for="exampleInputName">Title</label>
                    <input type="text" name="title" id="title"  class="form-control"/> 
                  </div> 
                  <div class="form-group">
                    <label for="exampleInputName">Paragraph</label>
                    <input type="text" name="paragraph" id="paragraph"  class="form-control"/> 
                  </div>
                  <div class="form-group">
                    <label for="exampleInputName">Date</label>
                    <input type="date" name="date" id="date"  class="form-control"/> 
                  </div> 
                  <div class="form-group">
                    <label for="exampleInputName">Image</label>
                    <input type="file" accept=".jpg, .png" name="image" id="photo"  class="form-control"/> 
                  </div>  
                  <div class="form-group">
                    <label for="exampleInputName">Link(Optional)</label>
                    <input type="url" name="link" id="link"  class="form-control"/> 
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