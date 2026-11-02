@php
//dd($response);
@endphp
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
                @if(!empty($admin_id ))
                  <h3 class="card-title">Edit Setting</h3>
			          
			          @endif
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form method="POST" action="/admin/managesite/editsite" enctype="multipart/form-data">
			          @csrf
       
                <input type="hidden" name="admin_id" value="@if(!empty($admin_id)){{$admin_id}}@endif">
                <div class="card-body">
            
				          <div class="form-group">
                    <label for="exampleInputName">Company Name</label>
                    <input type="text" name="name" class="form-control" id="exampleInputName" placeholder="name" value="@if($response !=''){{ $response->name}}@endif" required>
                  </div>

                  <div class="form-group">
                    <label for="exampleInputName">Email</label>
                    <input type="email" name="username" class="form-control" id="exampleInputName" placeholder="email" value="@if($response !=''){{ $response->username}}@endif" required>
                  </div>

                  <div class="form-group">
                    <label for="exampleInputName">Phone Number</label>
                    <input type="text" name="adminphoneno" class="form-control" id="exampleInputName" placeholder="phone number" value="@if($response !=''){{ $response->adminphoneno}}@endif" required>
                  </div>
                  <div class="form-group">
                    <label for="exampleInputName">Facebook</label>
                    <input type="text" name="facebook" class="form-control" id="exampleInputName" placeholder="Facebook" value="@if($response !=''){{ $response->facebook}}@endif" required>
                  </div>
                  <div class="form-group">
                    <label for="exampleInputName">Instagram</label>
                    <input type="text" name="instagram" class="form-control" id="exampleInputName" placeholder="Instagram" value="@if($response !=''){{ $response->instagram}}@endif" required>
                  </div>
                  <div class="form-group">
                    <label for="exampleInputName">Twitter</label>
                    <input type="text" name="twitter" class="form-control" id="exampleInputName" placeholder="Twitter" value="@if($response !=''){{ $response->twitter}}@endif" required>
                  </div>
                  <div class="form-group">
                    <label for="exampleInputName">Linkedin</label>
                    <input type="text" name="linkedin" class="form-control" id="exampleInputName" placeholder="Linkedin" value="@if($response !=''){{ $response->linkedin}}@endif" required>
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
/* $(function(){
    $("input[type='submit']").click(function(){
        var $fileUpload = $("input[type='file']");
        if (parseInt($fileUpload.get(0).files.length)>5){
         alert("You can only upload a maximum of 5 files");
        }
    });    
});​ */
</script>
 <script>
           /*  $(document).ready(() => {
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
            }); */
        </script>