<div class="modal fade" id="edit-books-you-should-read-{{ $books_you_should_read->id }}">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="{{ route('user.books-you-should-read.update', ['id' => $books_you_should_read->id]) }}" method="POST">
        @csrf
        
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title">Edit 3 Books You Should Read</h4>
        </div>
        
        <div class="modal-body">
          <div class="row">
            <div class="col-sm-12">
              <div class="form-group">
                <label for="">3 Books You Should Read</label>
                <textarea name="content" id="" required="" class="form-control" rows="8" />{{ $books_you_should_read->content }}</textarea>
              </div>
            </div>   
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Update Books</button>
        </div>
      </form>
    </div>
  </div>
</div>