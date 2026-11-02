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
                            <h3 class="card-title">Add App Version</h3>
                    	@else
                    	<h3 class="card-title">Add App Version</h3>
                    	@endif

              </div>

			  

              <!-- /.card-header -->

              <!-- form start -->

              <form method="POST" action="{{url('admin/add/app-version')}}" enctype="multipart/form-data">
                @if(!empty($response))
				 <input type="hidden" name="id" value="{{$response->id}}">
				@endif
			          @csrf
                <div class="form-group">
                    <label for="title">Popup Title</label>
                    <input type="text" name="title" id="title" value="@if(!empty($response)){{$response->title}}@else{{'New Blockrium update available'}}@endif" class="form-control"/>
                  </div>

                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="version">Display Version</label>
                        <input type="text" name="version" id="version" value="@if(!empty($response)){{$response->version}}@endif" class="form-control" placeholder="Example: 1.10.30 or 60"/>
                        <small class="text-muted">Shown to users. Keep this friendly.</small>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="latest_version_code">Latest Version Code</label>
                        <input type="number" name="latest_version_code" id="latest_version_code" value="@if(!empty($response) && isset($response->latest_version_code)){{$response->latest_version_code}}@elseif(!empty($response)){{$response->version}}@endif" class="form-control" placeholder="Example: 60"/>
                        <small class="text-muted">Must be higher than the installed app version code.</small>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="minimum_version_code">Minimum Version Code</label>
                        <input type="number" name="minimum_version_code" id="minimum_version_code" value="@if(!empty($response) && isset($response->minimum_version_code)){{$response->minimum_version_code}}@else{{0}}@endif" class="form-control" placeholder="Example: 45"/>
                        <small class="text-muted">Users below this cannot dismiss the popup. Use 0 for optional.</small>
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="platform">Platform</label>
                        <select name="platform" id="platform" class="form-control">
                          @php $platform = !empty($response) && isset($response->platform) ? $response->platform : 'android'; @endphp
                          <option value="android" @if($platform === 'android') selected @endif>Android</option>
                          <option value="ios" @if($platform === 'ios') selected @endif>iOS</option>
                          <option value="all" @if($platform === 'all') selected @endif>All</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="form-check">
                          <input type="checkbox" name="force_update" id="force_update" value="1" class="form-check-input" @if(!empty($response) && !empty($response->force_update)) checked @endif>
                          <label class="form-check-label" for="force_update">Force update</label>
                        </div>
                        <small class="text-muted">If checked, users cannot press Later.</small>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="form-check">
                          <input type="checkbox" name="status" id="status" value="1" class="form-check-input" @if(empty($response) || !isset($response->status) || !empty($response->status)) checked @endif>
                          <label class="form-check-label" for="status">Active</label>
                        </div>
                        <small class="text-muted">Inactive records are ignored by the API.</small>
                      </div>
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="android_link">Play Store Link</label>
                    <input type="text" name="android_link" id="android_link" value="@if(!empty($response) && isset($response->android_link)){{$response->android_link}}@elseif(!empty($response)){{$response->link}}@else{{'https://play.google.com/store/apps/details?id=com.blockrium'}}@endif" class="form-control"/>
                  </div>

                  <input type="hidden" name="link" id="link" value="@if(!empty($response)){{$response->link}}@else{{'https://play.google.com/store/apps/details?id=com.blockrium'}}@endif">

                  <div class="form-group">
                    <label for="release_notes">Release Notes</label>
                    <textarea name="release_notes" id="release_notes" class="form-control" rows="4" placeholder="Tell users what changed.">@if(!empty($response) && isset($response->release_notes)){{$response->release_notes}}@endif</textarea>
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
