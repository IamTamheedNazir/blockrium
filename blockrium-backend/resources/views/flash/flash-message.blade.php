@if ($message = Session::get('success'))
<div class="alert alert-success alert-block">
	<button type="button" class="close" data-dismiss="alert">×</button>	
        <strong>{{ $message }}</strong>
</div>
@endif


@if ($message = Session::get('error'))

<div class="alert bg-danger alert-dismissible mb-2" role="alert">
<button type="button" class="close" data-dismiss="alert">×</button>	
<strong>{{ $message }}</strong>
</div>
@endif


@if ($message = Session::get('warning'))
<div class="alert alert-warning alert-block">
	<button type="button" class="close" data-dismiss="alert">×</button>	
	<strong>{{ $message }}</strong>
</div>
@endif


@if ($message = Session::get('info'))
<div class="alert alert-info alert-block">
	<button type="button" class="close" data-dismiss="alert">×</button>	
	<strong>{{ $message }}</strong>
</div>
@endif


@if ($errors->any())
<div class="alert alert-danger">
	<button type="button" class="close" data-dismiss="alert"></button>	
	Please check the form below for errors
</div>
@endif

@if ($message = Session::get('changesuccess'))
<div role="alert" aria-live="assertive" aria-atomic="true" class="toast  alert-success" data-autohide="false">
  <div class="toast-header">
   
    <strong class="mr-auto" style="margin-right: auto!important;">Success</strong>
    <!--<small>11 mins ago</small>-->
    <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
  <div class="toast-body">
   {{ $message }}
  </div>
</div>
@endif