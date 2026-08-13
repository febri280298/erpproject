@extends('layouts.app')

@section('title', $employee->exists ? 'Ubah Karyawan' : 'Tambah Karyawan')
@section('pretitle', 'SDM')

@section('content')
    <form method="POST" enctype="multipart/form-data"
          action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}">
        @csrf
        @if($employee->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <x-card title="Data Pribadi">
                    <div class="row g-3">
                        <x-form.input name="nik" label="NIK" :value="$employee->nik" required col="col-md-4" placeholder="EMP-0001" />
                        <x-form.input name="name" label="Nama Lengkap" :value="$employee->name" required col="col-md-8" />

                        <x-form.select name="gender" label="Jenis Kelamin" :value="$employee->gender" col="col-md-4"
                                       :options="['L' => 'Laki-laki', 'P' => 'Perempuan']" />
                        <x-form.input name="birth_date" label="Tanggal Lahir" type="date"
                                      :value="optional($employee->birth_date)->toDateString()" col="col-md-4" />
                        <x-form.input name="phone" label="Telepon" :value="$employee->phone" col="col-md-4" />

                        <x-form.input name="email" label="Email" type="email" :value="$employee->email" col="col-md-6" />
                        <x-form.input name="npwp" label="NPWP" :value="$employee->npwp" col="col-md-6" />

                        <x-form.textarea name="address" label="Alamat" :value="$employee->address" rows="2" />
                    </div>
                </x-card>

                <x-card title="Data Kepegawaian" class="mt-3">
                    <div class="row g-3">
                        <x-form.select name="department_id" label="Departemen" :options="$departments"
                                       :value="$employee->department_id" col="col-md-4" />
                        <x-form.select name="position_id" label="Jabatan" :options="$positions"
                                       :value="$employee->position_id" col="col-md-4" />
                        <x-form.select name="employment_type" label="Status Kepegawaian" :value="$employee->employment_type"
                                       required col="col-md-4" :placeholder="false"
                                       :options="['permanent' => 'Tetap', 'contract' => 'Kontrak', 'intern' => 'Magang']" />

                        <x-form.input name="join_date" label="Tanggal Bergabung" type="date"
                                      :value="optional($employee->join_date)->toDateString()" col="col-md-4" />
                        <x-form.input name="resign_date" label="Tanggal Resign" type="date"
                                      :value="optional($employee->resign_date)->toDateString()" col="col-md-4" />
                        <x-form.select name="status" label="Status" :value="$employee->status" required col="col-md-4" :placeholder="false"
                                       :options="['active' => 'Aktif', 'resigned' => 'Resign', 'terminated' => 'Diberhentikan']" />

                        <x-form.select name="user_id" label="Akun Pengguna" :options="$users" :value="$employee->user_id"
                                       col="col-md-6" placeholder="— Tidak terhubung —"
                                       help="Hubungkan agar karyawan bisa login ke sistem." />
                    </div>
                </x-card>
            </div>

            <div class="col-lg-4">
                <x-card title="Gaji & Rekening">
                    <div class="row g-3">
                        <x-form.input name="basic_salary" label="Gaji Pokok" type="number" step="0.01"
                                      :value="$employee->basic_salary ?? 0" required col="col-12" prefix="Rp" />
                        <x-form.input name="allowance" label="Tunjangan" type="number" step="0.01"
                                      :value="$employee->allowance ?? 0" col="col-12" prefix="Rp" />
                        <x-form.input name="bank_name" label="Nama Bank" :value="$employee->bank_name" col="col-12" />
                        <x-form.input name="bank_account" label="No. Rekening" :value="$employee->bank_account" col="col-12" />
                    </div>
                </x-card>

                <x-card title="Foto" class="mt-3">
                    @if($employee->photoUrl())
                        <img src="{{ $employee->photoUrl() }}" alt="{{ $employee->name }}" class="img-fluid rounded mb-2">
                    @endif
                    <input type="file" name="photo" class="form-control" accept="image/*">
                    @error('photo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </x-card>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end mt-3 mb-4">
            <a href="{{ route('employees.index') }}" class="btn btn-link">Batal</a>
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Simpan</button>
        </div>
    </form>
@endsection
