<div class="modal fade" id="delete-world-i-desire-{{ $world_i_desire->id }}">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="{{ route('user.world-i-desire.delete', ['id' => $world_i_desire->id ]) }}" method="POST">
        @csrf
        
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title">Delete The World I Desire to See?</h4>
        </div>
        
        <div class="modal-body">
          <h3>Delete The World I Desire to See?</h3>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-danger">Yes, Delete Message</button>
        </div>
      </form>
    </div>
  </div>
</div>