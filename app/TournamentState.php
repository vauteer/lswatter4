<?php

namespace App;

enum TournamentState: string
{
    case Registering = 'registering';
    case Drawn = 'drawn';
    case Running = 'running';
    case Finished = 'finished';
}
