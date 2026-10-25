<footer class="footer">
    <div class="container-fluid">
        <div class="row">
            @if(($backofficeMode ?? 'existing') !== 'existing')
            <div class="col-sm-6">&copy; {{ date('Y') }} HC {{ $backofficeMode === 'dealership' ? 'Dealership' : 'Trading Hub' }}</div>
            <div class="col-sm-6"><div class="text-sm-end d-none d-sm-block">Operations workspace</div></div>
            @else
            <div class="col-sm-6">
        Copyright © 2023.
            </div>
            <div class="col-sm-6">
                <div class="text-sm-end d-none d-sm-block">
                    Crafted with <i class="mdi mdi-heart text-danger"></i> by Hc Gaming Studio
                </div>
            </div>
            @endif
        </div>
    </div>
</footer>
