@include('admin.includes.header')
@include('admin.includes.sidebar')
 <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row ">
		  <div class="col-md-9">
       <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Reset Password</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form method="POST" action="admin/change_password">
			  @csrf
			  <input type="hidden" name="id" value="@if(!empty($id)){{$id}}@endif">
                <div class="card-body">
				<span id="message">  </span>
                  <div class="form-group">
                    <label for="exampleInputName">New Password</label>
                    <input type="password" value="" name ="new_password" id="password" class="form-control" placeholder="New Password">
					
                  </div>
				  <div class="form-group">
                    <label for="exampleInputName">Confirm Password</label>
                    <input type="password" value="" name ="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm Password">
                  </div> 
			
				 
                </div>
                <!-- /.card-body -->

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
  
$(document).ready(function(){
$('#password, #confirm_password').on('change', function () {
  if ($('#password').val() == $('#confirm_password').val()) {
    $('#message').html('Matching').css('color', 'green');
  } else 
    $('#message').html('Not Matching').css('color', 'red');
});

});  


</script>