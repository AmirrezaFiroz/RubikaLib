<?php declare(strict_types=1);

/**
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2023 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

namespace danog\MadelineProto\EventHandler\Filter;

use Attribute;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\EventHandler\AbstractMessage;
use danog\MadelineProto\EventHandler\Update;

/**
 * Allow only messages coming from bots.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class FilterFromBot extends Filter
{
    private readonly EventHandler $API;
    public function initialize(EventHandler $API): Filter
    {
        $this->API = $API;
        return $this;
    }
    public function apply(Update $update): bool
    {
        return $update instanceof AbstractMessage && $this->API->isBot($update->senderId);
    }
}
