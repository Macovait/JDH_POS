import type { TeamMemberProps } from '@/lib/block-types';

export default function TeamMemberBlock({ members, columns = 3, layout = 'grid' }: TeamMemberProps) {
  const colClass = {
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
  };

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className={`grid gap-6 ${layout === 'list' ? 'grid-cols-1' : colClass[columns]}`}>
        {members.map((member, index) => (
          <div key={index} className="bg-white border border-gray-100 rounded-2xl p-5 text-center hover:shadow-sm transition">
            {member.image ? (
              <img
                src={member.image}
                alt={member.name}
                className="w-24 h-24 object-cover rounded-full mx-auto mb-4"
              />
            ) : (
              <div className="w-24 h-24 rounded-full bg-orange-50 flex items-center justify-center text-3xl mx-auto mb-4">
                👤
              </div>
            )}
            <h3 className="text-base font-bold text-gray-900">{member.name}</h3>
            {member.role && <p className="text-xs text-orange-500 font-medium mt-0.5">{member.role}</p>}
            {member.bio && <p className="text-sm text-gray-500 mt-2 line-clamp-3">{member.bio}</p>}
            {member.social && member.social.length > 0 && (
              <div className="flex items-center justify-center gap-3 mt-4">
                {member.social.map((s, i) => (
                  <a
                    key={i}
                    href={s.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-xs text-gray-400 hover:text-orange-500 transition"
                  >
                    {s.platform}
                  </a>
                ))}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
