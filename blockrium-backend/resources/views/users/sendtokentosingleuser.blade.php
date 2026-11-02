@include('admin.includes.header')

@include('admin.includes.sidebar') 

    

	<div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row ">
		            <div class="col-md-12">
                        <div class="card card-primary">
                        @include('flash/flash-message')
                            <div class="card-header">
				                <h3 class="card-title">Add Coin to Single User By Email</h3>			
                            </div>
                            <form method="POST" action="{{url('admin/add/single-user-addcoin')}}" enctype="multipart/form-data">

                                @csrf
                                <div class="form-group">
                                    <label for="exampleInputName">Enter user email</label>
                                    <input type="email" name="email" id="email"  class="form-control"/> 
                                </div>
                                
                                <div class="form-group">
                                    <label for="exampleInputName">Enter The Amount to send the user</label>
                                    <input type="number" name="token" id="token"  class="form-control"/> 
                                </div> 
                                                
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary">Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

@include('admin.includes.footer')
