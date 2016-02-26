-- ---------------------------------------------------------------------------------------------------------------------
-- Usage: next  <token>  <prefix> <required> <remaining>
-- ---------------------------------------------------------------------------------------------------------------------

if #KEYS ~= 1 then return redis.error_reply("ERR faulty dummy in 'lamport.next' script") end

-- extract token
local token = KEYS[1]

-- extract arguments
local prefix    = ARGV[1]
local required  = ARGV[2]
local remaining = ARGV[3]

-- extract data
local dataKey    = prefix .. ':data:{' .. token .. '}'
--
local currentIdx = tonumber(redis.call('HGET', dataKey, 'current'))
local salt       = tostring(redis.call('HGET', dataKey, 'salt'))

if currentIdx < required + remaining then return redis.error_reply("ERR not enough indexes left") end

-- extract hashes
local hashesKey = prefix .. ':hashes:{' .. token .. '}:' .. salt
--
local hashes    = redis.call('ZREVRANGEBYSCORE', hashesKey, currentIdx - 1, currentIdx - required)

-- update data
redis.call('HSET', dataKey, 'current', currentIdx - required)


return hashes
