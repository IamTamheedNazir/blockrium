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
			          @if(!empty($id))
                  <h3 class="card-title">Edit Game Category</h3>
			          @else
				          <h3 class="card-title">Add Game Category</h3>
			          @endif
              </div>
              <!-- /.card-header -->
			        @include('flash/flash-message')
				
              <!-- form start -->
                <form action="{{url('admin/add/game-category')}}" method="post"  enctype="multipart/form-data">
			            @csrf
			            <input type="hidden" name="id" value="@if(!empty($id)){{$id}}@endif">
                    <div class="card-body">
                      
                      <div class="form-group">
                        <label for="exampleInputName">Game Category</label>
                        <input type="text" name="title" class="form-control" id="exampleInputName" placeholder="Enter Game Category Name" value="@if($response !=''){{ $response->title}}@endif" required>
                      </div>
                     
                    </div>
                
                    <!-- /.card-body -->
			
                    <div class="card-footer">
                        <button id="payment-button" type="submit" class="btn btn-lg btn-info">
                            Submit
					    </button>
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
