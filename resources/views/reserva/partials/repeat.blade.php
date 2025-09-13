<div class="row" id="repeat_container">
    <div class="col-sm form-group">
        <b>Dias da repetição</b>
        <div class="checkFlex">
            @include('reserva.partials.checkFlex', ['name' => "repeat_days[1]", 'type' => "checkbox", 'value' => "1", 'label' => "Seg"])
            @include('reserva.partials.checkFlex', ['name' => "repeat_days[2]", 'type' => "checkbox", 'value' => "2", 'label' => "Ter"])
            @include('reserva.partials.checkFlex', ['name' => "repeat_days[3]", 'type' => "checkbox", 'value' => "3", 'label' => "Qua"])
            @include('reserva.partials.checkFlex', ['name' => "repeat_days[4]", 'type' => "checkbox", 'value' => "4", 'label' => "Qui"])
            @include('reserva.partials.checkFlex', ['name' => "repeat_days[5]", 'type' => "checkbox", 'value' => "5", 'label' => "Sex"])
            @include('reserva.partials.checkFlex', ['name' => "repeat_days[6]", 'type' => "checkbox", 'value' => "6", 'label' => "Sáb"])
            @include('reserva.partials.checkFlex', ['name' => "repeat_days[7]", 'type' => "checkbox", 'value' => "7", 'label' => "Dom"])
        </div>
        <small class="form-text text-muted">Selecione os dias da semana que o evento deve se repetir.</small>
    </div>
    <div class="col-sm form-group">
        <label for="" class="required"><b> Repetição semanal até:</b></label>
        <br>
        <input type="text" class="datepicker" id="repFormControl" name="repeat_until" value="{{  old('repeat_until', $reserva->repeat_until) }}">
        <small class="form-text text-muted">Formato: 30/12/2021</small>
    </div>
</div>

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
                        $existing_day_times = json_decode($reserva->day_times ?? '{}', true);
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
                                <label class="required">Horário de início</label>
                                <input class="form-control" type="text" name="day_times[{{ $day_number }}][start]"
                                       value="{{ old('day_times.' . $day_number . '.start', $existing_day_times[$day_number]['start'] ?? '') }}">
                                <small class="form-text text-muted">Formato: 9:00</small>
                            </div>
                            <div class="col-sm-4">
                                <label class="required">Horário de fim</label>
                                <input class="form-control" type="text" name="day_times[{{ $day_number }}][end]"
                                       value="{{ old('day_times.' . $day_number . '.end', $existing_day_times[$day_number]['end'] ?? '') }}">
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