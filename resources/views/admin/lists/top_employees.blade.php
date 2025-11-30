<div class="box box-info">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $title }}</h3>
    </div>
    <div class="box-body no-padding">
        <table class="table table-striped">
            <tbody>
                <tr>
                    <th style="width: 10px">#</th>
                    <th>User</th>
                    <th style="width: 40px">Count</th>
                </tr>
                @foreach($users as $index => $user)
                <tr>
                    <td>{{ $index + 1 }}.</td>
                    <td>{{ $user->name }}</td>
                    <td><span class="badge bg-red">{{ $user->{$count_field} }} {{ $count_label }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>