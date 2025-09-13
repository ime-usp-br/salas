@extends('main')

@section('content')
    <form id="form-reserva"  method="POST" action="/reservas/{{ $reserva->id }}" data-ajax="{{ route('SenhaunicaFindUsers') }}">
        @csrf
        @method('patch')
        @include('reserva.partials.form', ['title' => "Editar reserva"])
    </form>
@endsection

@section('javascripts_bottom')

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script type="text/javascript">

        function collapse() {
            document.getElementById("repeat_container").style.display = "flex";
        }

        function hide() {
            document.getElementById("repeat_container").style.display = "none";
        }
        
        $('#rep_bool_Sim').click( function() {
                $('#repeat_container').show();
                $('#per_day_times_container').show();
                $('#global_time_fields').hide();

                // Change "Data" label to "Data inicial" when repetition is enabled
                $('input[name="data"]').closest('.form-group').find('label b').text('Data inicial');

                // Make global time fields not required when using per-day times but keep them for validation
                $('input[name="horario_inicio"]').removeAttr('required');
                $('input[name="horario_fim"]').removeAttr('required');
            }
        );

        $('#rep_bool_Nao').click( function() {
                $('#repeat_container').hide();
                $('#per_day_times_container').hide();
                $('#global_time_fields').show();
                $('#repFormControl').val('');

                // Change label back to "Data" when repetition is disabled
                $('input[name="data"]').closest('.form-group').find('label b').text('Data');

                // Make global time fields required again
                $('input[name="horario_inicio"]').attr('required', true);
                $('input[name="horario_fim"]').attr('required', true);

                // Hide all per-day time rows
                $('.day-time-row').hide();
            }
        );

        // Handle day checkbox changes to show/hide corresponding time fields
        $('input[name^="repeat_days"]').change(function() {
            var dayNumber = $(this).val();
            var dayTimeRow = $('#day_times_' + dayNumber);

            if ($(this).is(':checked')) {
                dayTimeRow.show();
                // Make time fields required for selected days
                dayTimeRow.find('input[type="text"]').attr('required', true);
            } else {
                dayTimeRow.hide();
                // Remove required attribute and clear values
                dayTimeRow.find('input[type="text"]').removeAttr('required').val('');
            }
        });

        window.onload = function() {
            if($('#rep_bool_Sim').is(':checked')){
                $('#repeat_container').show();
                $('#per_day_times_container').show();
                $('#global_time_fields').hide();

                // Change "Data" label to "Data inicial" when repetition is enabled
                $('input[name="data"]').closest('.form-group').find('label b').text('Data inicial');

                // Show time rows for already checked days
                $('input[name^="repeat_days"]:checked').each(function() {
                    $('#day_times_' + $(this).val()).show();
                });
            } else {
                $('#per_day_times_container').hide();
                $('#global_time_fields').show();
                // Ensure label is "Data" when repetition is disabled
                $('input[name="data"]').closest('.form-group').find('label b').text('Data');
            }
        }      

        $(document).ready(function() {
            $('.salas_select').select2();
        });

    </script>

    @include('reserva.partials.select2-responsaveis-js')
@stop
