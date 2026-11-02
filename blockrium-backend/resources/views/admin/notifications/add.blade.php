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

				        <h3 class="card-title">Send Mail</h3>			

              </div>

			  

              <!-- /.card-header -->

              <!-- form start -->

              <form method="POST" action="{{ url('admin/add/notification') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="mail_subject">Mail Subject</label>
                    <input type="text" name="mail_subject" id="mail_subject" class="form-control" required/>
                </div> 
                <div class="form-group">
                    <label for="mail_body">Mail Body</label>
                    <textarea name="mail_body" id="mail_body" class="form-control" rows="6" required></textarea>
                    <small class="form-text text-muted">You can use HTML tags here (e.g., <strong>&lt;b&gt;</strong>, <strong>&lt;i&gt;</strong>)</small>
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


<script src="https://cdn.ckeditor.com/4.25.0/standard/ckeditor.js"></script>
<script>
    CKEDITOR.replace('mail_body');
</script>