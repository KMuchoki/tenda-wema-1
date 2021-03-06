<div class="modal fade" id="add-hobby">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="{{ route('user.hobby.add') }}" method="POST">
        @csrf
        
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title">Add Hobby</h4>
        </div>
        
        <div class="modal-body">
          <div class="row">
            <div class="col-sm-12">
              <div class="form-group">
                <label for="hobby-1">Hobby</label>
                <textarea rows="8" name="content" id="hobby-1" required="" class="form-control" /></textarea>
              </div>
            </div>
            
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Add Hobby</button>
        </div>
      </form>
    </div>
  </div>
</div>