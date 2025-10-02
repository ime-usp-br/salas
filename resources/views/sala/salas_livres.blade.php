@extends('main')
@section('content')
<div class="card">
  <div class="card-header"><b>Procurar por salas disponíveis</b></div>
    <div class="card-body">
      <form method="GET" action="{{ route('salas.livres') }}" id="form-search">
        @csrf

        <!-- Recurring search toggle -->
        <div class="row mb-3">
          <div class="col">
            <label><b>Busca recorrente?</b></label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="rep_bool" id="rep_bool_Nao" value="Não" checked>
              <label class="form-check-label" for="rep_bool_Nao">Não</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="rep_bool" id="rep_bool_Sim" value="Sim">
              <label class="form-check-label" for="rep_bool_Sim">Sim</label>
            </div>
            <small class="form-text text-muted">Busque salas disponíveis para um padrão recorrente com horários diferentes por dia.</small>
          </div>
        </div>

        <div class="row">
          <div class="col-md-3 form-group">
            <label><b id="label_data">Data</b></label>
            <input type="text" class="datepicker form-control" id="data" name="data">
            <small class="text-muted">Ex.: {{ $today }}</small>
          </div>

          <div class="col-md-7 form-group" id="global_time_fields">
            <div class="row">
              <div class="col-6">
                <label>Horário início</label>
                <input type="text" name="horario_inicio" id="horario_inicio" class="form-control">
                <small class="text-muted">Formato 9:00</small>
              </div>
              <div class="col-6">
                <label>Horário fim</label>
                <input type="text" name="horario_fim" id="horario_fim" class="form-control">
                <small class="text-muted">Formato 9:00</small>
              </div>
            </div>
          </div>

          <div class="col-md-2" style="margin-top:30px;">
            <button name="search" id="search" type="submit" class="btn btn-success"><i class="fas fa-search"></i></button>
          </div>
        </div>

        <!-- Recurring pattern fields -->
        <div class="row" id="repeat_container" style="display: none;">
          <div class="col-sm form-group">
            <b>Dias da repetição</b>
            <div class="checkFlex">
              @php
                // Create a dummy object for the checkFlex partial (search page doesn't have $reserva)
                $reserva = (object)['parent_id' => null, 'repeat_days' => []];
              @endphp
              @include('reserva.partials.checkFlex', ['name' => "repeat_days[1]", 'type' => "checkbox", 'value' => "1", 'label' => "Seg"])
              @include('reserva.partials.checkFlex', ['name' => "repeat_days[2]", 'type' => "checkbox", 'value' => "2", 'label' => "Ter"])
              @include('reserva.partials.checkFlex', ['name' => "repeat_days[3]", 'type' => "checkbox", 'value' => "3", 'label' => "Qua"])
              @include('reserva.partials.checkFlex', ['name' => "repeat_days[4]", 'type' => "checkbox", 'value' => "4", 'label' => "Qui"])
              @include('reserva.partials.checkFlex', ['name' => "repeat_days[5]", 'type' => "checkbox", 'value' => "5", 'label' => "Sex"])
              @include('reserva.partials.checkFlex', ['name' => "repeat_days[6]", 'type' => "checkbox", 'value' => "6", 'label' => "Sáb"])
              @include('reserva.partials.checkFlex', ['name' => "repeat_days[7]", 'type' => "checkbox", 'value' => "7", 'label' => "Dom"])
            </div>
            <small class="form-text text-muted">Selecione os dias da semana que a busca deve considerar.</small>
          </div>
          <div class="col-sm form-group">
            <label><b>Repetição semanal até:</b></label>
            <br>
            <input type="text" class="datepicker" id="repeat_until" name="repeat_until">
            <small class="form-text text-muted">Formato: 30/12/2025</small>
          </div>
        </div>

        <!-- Per-day time fields -->
        <div id="per_day_times_container" style="display: none;">
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <b>Horários específicos por dia</b>
                  <small class="form-text text-muted">Configure horários diferentes para cada dia da semana.</small>
                </div>
                <div class="card-body">
                  @php
                    $days = [
                      '1' => 'Segunda-feira',
                      '2' => 'Terça-feira',
                      '3' => 'Quarta-feira',
                      '4' => 'Quinta-feira',
                      '5' => 'Sexta-feira',
                      '6' => 'Sábado',
                      '7' => 'Domingo'
                    ];
                  @endphp

                  @foreach($days as $day_number => $day_name)
                  <div class="day-time-row" id="day_times_{{ $day_number }}" style="display: none;">
                    @if(!$loop->first)
                      <hr class="my-3">
                    @endif
                    <div class="row">
                      <div class="col-sm-3">
                        <label><b>{{ $day_name }}</b></label>
                      </div>
                      <div class="col-sm-4">
                        <label>Horário de início</label>
                        <input class="form-control" type="text" name="day_times[{{ $day_number }}][start]">
                        <small class="form-text text-muted">Formato: 9:00</small>
                      </div>
                      <div class="col-sm-4">
                        <label>Horário de fim</label>
                        <input class="form-control" type="text" name="day_times[{{ $day_number }}][end]">
                        <small class="form-text text-muted">Formato: 11:00</small>
                      </div>
                    </div>
                  </div>
                  @endforeach
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><b>Salas disponíveis</b></div>
      <div class="spinner-border m-5 d-none" role="status" id="spinner">
        <span class="sr-only">Carregando...</span>
      </div>
    <div class="card-body ml-5" id="disponivel">
    </div>
  </div>
</div>
@endsection('content')

@section('javascripts_bottom')
  <script>
    $(document).ready(function(){
      // Handle recurring search toggle
      $('#rep_bool_Sim').click(function() {
        $('#repeat_container').show();
        $('#per_day_times_container').show();
        $('#global_time_fields').hide();
        $('#label_data').text('Data inicial');

        // Make global time fields not required when using per-day times
        $('#horario_inicio, #horario_fim').removeAttr('required');
      });

      $('#rep_bool_Nao').click(function() {
        $('#repeat_container').hide();
        $('#per_day_times_container').hide();
        $('#global_time_fields').show();
        $('#label_data').text('Data');
        $('#repeat_until').val('');

        // Make global time fields required again
        $('#horario_inicio, #horario_fim').attr('required', true);

        // Hide and clear all per-day time rows
        $('.day-time-row').hide();
        $('.day-time-row input').val('');
        $('input[name^="repeat_days"]').prop('checked', false);
      });

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

      // AJAX form submission
      $('#form-search').on('submit', function(e) {
        e.preventDefault()

        $.ajax({
          url: $(this).attr('action'),
          type: $(this).attr('method'),
          data: $(this).serialize(),
          beforeSend: function() {
            $("#disponivel").empty();
            $("#spinner").removeClass('d-none');
          },
          success: function(response) {
            clearMsg();
            var html = '';

            // Check if response is empty
            var hasResults = false;
            $.each(response, function(index, categoria) {
              if (categoria.length > 0) {
                hasResults = true;
                return false; // break loop
              }
            });

            if (!hasResults) {
              html = '<div class="alert alert-info">Nenhuma sala encontrada que atenda a todos os horários do padrão recorrente solicitado.</div>';
            } else {
              $.each(response, function(index, categoria) {
                if (categoria.length > 0) {
                  html += `<b>${index}</b><ul class="list-unstyled">`;
                  $.each(categoria, function(index, item) {
                    html += `<li class="ml-5 mt-2"><a href="/salas/${item.id}">${item.nome}</a> - capacidade: ${item.capacidade} pessoas</li>`;
                  });
                  html += `</ul>`;
                }
              });
            }

            $("#disponivel").append(html);
          },
          error: function(response) {
            clearMsg();
            $(".flash-message").append("<div class='alert alert-danger'><ul></ul></div>");
            $.each(response.responseJSON.errors, function(key, value) {
              $(".alert-danger ul").append('<li>' + value + '</li>');
            });
          }
        });

      });
    });

    function clearMsg() {
      $(".flash-message").html('');
      $("#spinner").addClass('d-none');
    }
  </script>
@endsection
