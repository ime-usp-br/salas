<style>
    .rectangle {
        height: 50px;
        width: 50px;
        background-color: #ffffff;
        border: 2px solid #ffffff;
        border-radius: 5px;
    }

    #reserva-header {
        display: flex;
        justify-content: space-between;
    }

    .adm-icons {
        width: 30%;
        display: flex;
        justify-content: end;
    }
</style>

@if ($reserva->status == 'pendente')
   <div style="background-color: {{config('salas.cores.pendente')}}" class="p-2 mb-2 rounded">
     Pendente    
   </div>
@endif

<div class="card">
    <div class="card-header" id="reserva-header">
        <div>
            <b>{{ $reserva->nome }}</b>
        </div>
        @can('owner', $reserva)
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Excluir Reserva</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                </div>

                <!-- Modifica a pergunta de exclusão caso a reserva tenha repetições -->
                @if (is_null($reserva->parent_id))
                    <div class="modal-body">
                        Deseja realmente excluir esta reserva?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger" id="btn-excluir">Excluir</button>
                    </div>
                @else
                    <div class="modal-body">
                        Deseja excluir somente esta instância ou todas as repetições da reserva?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-purge btn btn-secondary">Somente esta intância</button>
                        <button type="button" class="btn-purge btn btn-danger" data-purge="1">Todas as repetições</button>
                    </div>
                @endif

            </div>
            </div>
        </div>
        <div class="adm-icons">
            <div>
                <form action="/reservas/{{  $reserva->id  }}" method="POST" id="form-excluir">
                    @csrf
                    @method('delete')
                    @can('reserva.editar', $reserva)
                        <a class="btn btn-success" href="/reservas/{{  $reserva->id  }}/edit" title="Editar">
                            <i class="fa fa-pen"></i>
                        </a>
                    @endcan

                    @if(!is_null($reserva->parent_id)) <input type="hidden" name="purge" id="purge"> @endif

                    <button data-toggle="modal" data-target="#deleteModal" class="btn btn-danger" type="button" title="Excluir" >
                        <i class="fa fa-trash" ></i>
                    </button>
                </form>
            </div>
        </div>
        @endcan
    </div>
    <div class="card-body">
        <div class="col-sm form-group">
        </div>
        <table class="table table-borderless">
            <div class="table-responsive">
                <tr>
                    <th>Cadastrada por</th>
                    <th>Responsáveis</th>
                    <th>Data</th>
                    <th>Horário</th>
                    <th>Sala</th>
                    <th>Descrição</th>
                    <th>Finalidade</th>
                </tr>
                <tr>
                    <td>{{ $reserva->user->name }} - {{ $reserva->user->codpes }}</td>
                    <td>{{$reserva->responsaveis->count() > 0 ? $reserva->responsaveis->pluck('nome')->implode(', ') : 'Sem responsáveis'}}</td>
                    <td>{{ $reserva->data }}</td>
                    <td>
                        @if($reserva->horario_inicio && $reserva->horario_fim)
                            {{ $reserva->horario_inicio }} a {{ $reserva->horario_fim }}
                        @elseif($reserva->day_times)
                            <span class="text-muted">Horários distintos por dia</span>
                        @else
                            <span class="text-muted">Horários não definidos</span>
                        @endif
                    </td>
                    <td>
                        <a href="/salas/{{ $reserva->sala->id }}">{{  $reserva->sala->nome  }}</a>
                    </td>
                    <td>{{ $reserva->descricao ?: 'Sem descrição' }}</td>
                    <td>
                        @if(isset($reserva->finalidade))
                            <div style="background-color: {{ $reserva->finalidade->cor }}" class="p-2 mt-n2 rounded">{{$reserva->finalidade->legenda}}</div>
                        @else
                            <div style="background-color: #BDBDBD" class="p-2 mt-n2 rounded">Sem finalidade</div>
                        @endif
                    </td>
                </tr>
            </div>
        </table>

        @if($reserva->irmaos())
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><strong>Esta reserva faz parte de um bloco de reservas recorrentes</strong></h6>

                    @if($reserva->parent_id == $reserva->id)
                        <p class="mb-2">
                            <i class="fa fa-info-circle"></i>
                            Você está visualizando a <strong>reserva principal</strong> do bloco.
                        </p>
                    @else
                        <p class="mb-2">
                            <i class="fa fa-info-circle"></i>
                            Esta é uma ocorrência individual.
                            <a href="/reservas/{{ $reserva->parent_id }}" class="alert-link">
                                Clique aqui para ver a reserva principal
                            </a>
                        </p>
                    @endif

                    @if($reserva->parent_id == $reserva->id && $reserva->day_times)
                        <div class="mb-3">
                            <strong>Padrão de horários distintos por dia:</strong>
                            <ul class="mb-0">
                                @php
                                    $days_map = [
                                        '1' => 'Segunda-feira',
                                        '2' => 'Terça-feira',
                                        '3' => 'Quarta-feira',
                                        '4' => 'Quinta-feira',
                                        '5' => 'Sexta-feira',
                                        '6' => 'Sábado',
                                        '7' => 'Domingo'
                                    ];
                                @endphp
                                @foreach($reserva->day_times as $day => $times)
                                    <li>{{ $days_map[$day] }}: {{ $times['start'] }} às {{ $times['end'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <strong>Todas as ocorrências deste bloco:</strong>
                    @php
                        $reservas_array = $reserva->irmaos()->toArray();
                        usort($reservas_array, function($a, $b) {
                            return strtotime(str_replace('/', '-', $a['data'])) - strtotime(str_replace('/', '-', $b['data']));
                        });
                    @endphp

                    <div class="row mt-2">
                        @foreach($reservas_array as $reservaIterator)
                            <div class="col-md-4 mb-2">
                                <div class="card {{ $reservaIterator['id'] == $reserva->id ? 'border-primary' : '' }}">
                                    <div class="card-body p-2">
                                        <small>
                                            <a href="/reservas/{{ $reservaIterator['id'] }}"
                                               class="{{ $reservaIterator['id'] == $reserva->id ? 'text-primary font-weight-bold' : '' }}">
                                                {{ $reservaIterator['data'] }}
                                            </a>
                                            <br>
                                            @if($reservaIterator['horario_inicio'] && $reservaIterator['horario_fim'])
                                                {{ $reservaIterator['horario_inicio'] }} - {{ $reservaIterator['horario_fim'] }}
                                            @else
                                                <span class="text-muted">Ver padrão acima</span>
                                            @endif
                                            @if($reservaIterator['id'] == $reserva->id)
                                                <span class="badge badge-primary">Atual</span>
                                            @endif
                                        </small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fa fa-exclamation-triangle"></i>
                            <strong>Atenção:</strong> Para alterar o padrão de horários, é necessário cancelar todo o bloco e criar uma nova reserva recorrente.
                            Você pode cancelar apenas esta ocorrência específica usando o botão "Excluir" acima.
                        </small>
                    </div>
                </div>
            </div>
        @endif
        <br>

        <br>
    </div>
</div>

@if ($reserva->status == 'pendente')
    @can('responsavel', $reserva->sala)
        <form action="{{route('reservas.destroy', $reserva)}}" method="POST" id="form-reserva-recusar" onsubmit="return confirm('Recusar reserva?')">
            @csrf
            @method('DELETE')
        </form>
        <div class="mt-4">
            <a class="btn btn-success" href="{{route('reservas.aprovar', $reserva)}}"><i class="fa fa-check"></i> Aprovar</a>
            <button class="btn btn-danger" form="form-reserva-recusar"><i class="fa fa-ban"></i> Recusar</button>
        </div>
    @endcan
@endif